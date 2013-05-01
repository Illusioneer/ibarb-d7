<?php

/**
 * @file
 * Plugin declaration for Rules panes, allowing Rules components to be embedded
 * as panes in Panels and similar modules.
 */

/**
 * Declaring the plugin.
 */
$plugin = array(
  // First the callback for describing the individual content panes.
  'content types' => 'rules_panes_content_types',

  // Then some description shared between all the panes.
  'title' => t('Rules component'),
  'edit form' => 'rules_panes_edit_form',
  'render callback' => 'rules_panes_render',
  'defaults' => array(
    'override_title' => FALSE,
    'button_text' => t('Execute'),
    'help_text' => t(''),
  ),
  'admin title' => 'rules_panes_admin_title',
  'admin info' => 'rules_panes_admin_info',
  'cache' => TRUE,
);

/**
 * Returns a list of all Rules components that can be used as content panes.
 */
function rules_panes_content_types($plugin_definition) {
  $content_types = array();
  foreach (rules_get_components(TRUE) as $name => $label) {
    $content_types[$name] = array(
      'title' => $label,
      'category' => array(t('Rules'), -3), // Dunno why this should be an array…
    );

    // Go through all the parameters used for this plugin, and declare them as
    // CTools contexts. Note that some parameter types don't have matching
    // context types, but should STILL be included to allow pairing up the data
    // when executing the component later.
    foreach (rules_config_load($name)->parameterInfo() as $parameter_info) {
      $ctools_name = rules_panes_convert_rules2ctools($parameter_info['type']);
      $content_types[$name]['required context'][] = new ctools_context_optional(t($parameter_info['label']), $ctools_name);
    }
  }

  return $content_types;
}

/**
 * Implements hook_admin_title().
 */
function rules_panes_admin_title($subtype, $conf, $context = NULL) {
  $component = rules_config_load($subtype);
  return t('Rules component: @label', array('@label' => $component->label));
}

/**
 * Implements hook_admin_info().
 */
function rules_panes_admin_info($subtype, $conf, $context = NULL) {
  // Load information about the Rules component.
  $component = rules_config_load($subtype);

  $info = new stdClass;
  $info->title = t('Parameter input');
  $info->content = '';

  // Loop through the parameters for the Rules componend, AND the contexts used
  // for this pane. (The twin arrays explains the need for the clunky counter.)
  $context_counter = -1;
  foreach ($component->parameterInfo() as $parameter) {
    $context_counter++;

    $info->content .= t($parameter['label']) . ' (' . t($parameter['type']) . '): ';
    if ($conf['context'][$context_counter] == 'empty') {
      $info->content .= t('Input by end user');
    }
    else {
      if (isset($context[$conf['context'][$context_counter]])) {
        $context_data = &$context[$conf['context'][$context_counter]];
        $info->content .= $context_data->identifier;
      }
      else {
        $info->content .= t('Missing context');
      }
    }
    $info->content .= '<br />';
  }
  return $info;
}

/**
 * Admin edit form for the content pane.
 */
function rules_panes_edit_form($form, $form_state) {
  // Create alias for the configuration, for improved code readability.
  $conf = &$form_state['conf'];

  $form['button_text'] = array(
    '#type' => 'textfield',
    '#title' => t('Button text'),
    '#default_value' => $conf['button_text'],
  );
  $form['help_text'] = array(
    '#type' => 'textfield',
    '#title' => t('Additional help text'),
    '#default_value' => $conf['help_text'],
  );
  return $form;
}

/**
 * Config submit function, to store form values in pane configuration.
 */
function rules_panes_edit_form_submit(&$form, &$form_state) {
  // Move important values to the 'conf' part of form_state, to allow them to
  // be saved. Note that the 'subtype_name' also goes here – this is the name
  // of the Rules component, and will be used when rendering the pane.
  $form_state['conf']['component'] = $form_state['values']['component'];
  $form_state['conf']['button_text'] = $form_state['values']['button_text'];
  $form_state['conf']['help_text'] = $form_state['values']['help_text'];
  $form_state['conf']['component'] = $form_state['subtype_name'];
}

/**
 * Renders the pane to execute Rules components.
 *
 * This is a small function – the heavy lifting is done by the form builder
 * function further down.
 */
function rules_panes_render($subtype, $conf, $args, $context) {
  $component = rules_config_load($conf['component']);
  $block = new stdClass();
  $block->title = t($component->label);
  $block->content = drupal_get_form('rules_panes_render_form', $conf, $context);
  return $block;
}

/**
 * Form builder function for the outward-facing form to execute components.
 *
 * A lot of code here is copied from Views Bulk Operations. Cred tobojanz and
 * infojunkie.
 *
 * @return
 *   The form array.
 */
function rules_panes_render_form($form, $form_state) {
  // Dig out some information we will need, and create a new temporary component
  // to be able to build a suitable form.
  $conf = &$form_state['build_info']['args'][0];
  $contexts = &$form_state['build_info']['args'][1];
  $component = rules_config_load($conf['component']);
  // The line here is magic to me. Thanks to VBO maintainers, again.
  $element = rules_plugin_factory($component instanceof RulesActionInterface ? 'action' : 'condition', 'component_' . $component->name);

  // Add information about what component is used in the form.
  $form['component'] = array(
    '#type' => 'value',
    '#value' => $conf['component'],
  );

  // Use direct input rather than data selection.
  foreach ($element->parameterInfo() as $name => $info) {
    $form_state['parameter_mode'][$name] = 'input';
  }

  // Add some extra stuff to the form.
  $form['exec_help'] = array(
    '#prefix' => '<p>',
    '#markup' => t($conf['help_text']),
    '#suffix' => '</p>',
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t($conf['button_text']),
    '#weight' => 20,
  );

  // Add the Rules stuff to the forms.
  $element->form($form, $form_state);

  // Loop through the parameters for the Rules component and either show a form
  // for manual entry, or insert a CTools context in its place.
  $context_counter = -1;
  foreach ($component->parameterInfo() as $name => $info) {
    // We're keeping track of two different arrays here. That's why this clunky
    // context counter variable is here. Sorry.
    $context_counter++;

    // If we don't have any CTools context set for this parameter, we will show
    // the form for manually entering a value. We tweak it to become more
    // end-user friendly.
    if (is_null($contexts[$context_counter]->data)) {
      // Remove the fieldset and move its title to the form element.
      if (isset($form['parameter'][$name]['settings'][$name]['#title'])) {
        $form['parameter'][$name]['#type'] = 'container';
        $form['parameter'][$name]['settings'][$name]['#title'] = $form['parameter'][$name]['#title'];
      }
      // Remove any elaborate help text (such as token replacement patterns).
      if (isset($form['parameter'][$name]['settings']['help'])) {
        unset($form['parameter'][$name]['settings']['help']);
      }

      // Hide the switch button if it's there.
      if (isset($form['parameter'][$name]['switch_button'])) {
        $form['parameter'][$name]['switch_button']['#access'] = FALSE;
      }
    }

    // If we DO have a CTools context for this parameter: get its ID, insert
    // into the form, and hide this part of the form.
    else {
      // If we have some kind of complex context type, CTools seems to describe
      // it with an array. It seems the last array entry corresponds fairly well
      // with what Rules/Entity API expects. *crossing fingers*
      if (is_array($contexts[$context_counter]->type)) {
        $type = end($contexts[$context_counter]->type);
        $wrapper = entity_metadata_wrapper(rules_panes_convert_ctools2rules($type), $contexts[$context_counter]->data);
        $id = $wrapper->getIdentifier();
      }
      else {
        // If we don't have any complex context type, let's try read off the
        // data right away. (At least this works for strings.)
        $id = (string) $contexts[$context_counter]->data;
      }
      $form['parameter'][$name]['settings'][$name]['#value'] = $id;
      $form['parameter'][$name]['#access'] = FALSE;
    }
  }

  // Re-use the validation callback, which will also populate the action with
  // the configuration settings in the form.
  $form['#validate'] = array('rules_ui_form_rules_config_validate');
  return $form;
}

/**
 * Submit function for the outward-facing pane, for executing the component.
 */
function rules_panes_render_form_submit($form, &$form_state) {
  $conf = &$form_state['build_info']['args'][0];
  $component = rules_config_load($conf['component']);
  $component->executeByArgs($form_state['rules_element']->settings);
}
