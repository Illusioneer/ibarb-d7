(function($) {

  Drupal.ajax = Drupal.ajax || {};
  Drupal.ajax.prototype.commands = Drupal.ajax.prototype.commands || {};

  Drupal.ajax.prototype.commands.asafReload = function (ajax, response, status) {
    var loc = response.window == 'parent' ? parent.location : location;
    loc.reload();
  }

  Drupal.ajax.prototype.commands.asafRedirect = function (ajax, response, status) {

    var loc = response.window == 'parent' ? parent.location : location;
    loc.href = response.href;
  }

})(jQuery);
