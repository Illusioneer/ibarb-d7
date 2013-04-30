<?php

global $wpdb, $wp, $current_user, $submission_target, $ok_filetypes, $theme_url;

global $length_options, $genre_options, $subcategory_options, $period_options;

global $form_email;

$theme_url = get_bloginfo('template_url');

$impulse_nonce = 'uioqdyUYUYGdwbbwu';

$submissions_table = 'submissions';

$submission_target = ABSPATH.'../submissions/';

$ok_filetypes = array(
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'text/plain',
    'application/pdf',
    'application/pdf;',
    'application/rtf',
    'text/rtf',
    'text/rtf;'
);

$max_file_size = sprintf('%s', 3 * pow(10,6));

$response_div = '';

$show_thankyou = false;

$genre_options = array(
    '-1'    => 'Choose one'
);

$genre_string = '';

$i = 1;

$sql = '
        SELECT
                description
        FROM
                bisac_codes
        WHERE
                1';

$sql = $wpdb -> prepare($sql);

$results = $wpdb -> get_results($sql);

foreach($results as $result){

    if($result -> description == 'Adult'){ continue; }

    $genre_options[$i] = sprintf('%s', $result -> description);

    $i++;

}


$length_options = array(
    '-1'    => 'Choose one',
    '1'             => '10-25K Words',
    '2'             => '25-50K Words',
    '3'             => '50-100K Words'
);

$subcategory_options = array(
    '-1'    => 'Choose one',
    '1'             => 'Gothic',
    '2'             => 'Steampunk',
    '3'             => 'Shifter',
    '4'             => 'Vampire',
    '5'             => 'Ghost',
    '6'             => 'Magical',
    '7'             => 'Futuristic',
    '8'             => 'Time Travel',
    '9'             => 'Western',
    '10'    => 'Small Town',
    '11'    => 'Suspense',
    '12'    => 'Fantasy',
    '13'    => 'Other'
);

$period_options = array(
    '-1'    => 'Choose one',
    '1'             => 'Prehistoric',
    '2'             => 'Medieval',
    '3'             => 'Regency',
    '4'             => 'Victorian',
    '5'             => 'Turn of the Century',
    '6'             => 'WWII',
    '7'             => '50s-Present',
    '8'             => 'Future',
    '9'             => 'Other'
);


$transfer_array = array();

$errors = array();

$fields_whitelist = array(
    'man_title'                     => array(1,'','%s'),
//        'man_email'                     => array(1,'','%s'),
    'man_name'                      => array(1,'','%s'),
    'finished'                      => array(1,'','%s'),
    'published'                     => array(1,'','%s'),
    'length'                        => array(1,'','%d'),
    'genre'                         => array(1,'','%d'),
    'subcategory'           => array(1,'','%d'),
    'period'                        => array(1,'','%d'),
    'submission_request'=> array(0,'','%s'),
    'synopsis'                      => array(1,'','%s'),
    'best_scene'            => array(1,'','%s'),
    'wp_nonce'                      => array(1,'','%s'),
    'query_letter'          => array(0,'','%s')
);

$fields_blacklist = array('wp_nonce', 'manuscript_file');

foreach($fields_whitelist as $key => $value_array){

    if(isset($_POST[$key]) && !empty($_POST[$key])){

        if(in_array($key, array('finished','published'))){

            if(preg_match('/yes$/', $_POST[$key])){

                $transfer_array[$key] = 1;

            }else{

                $transfer_array[$key] = 0;

            }

        }else{

            $transfer_array[$key] = sprintf($fields_whitelist[$key][2], $_POST[$key]);

        }

    }else{

        $transfer_array[$key] = $value_array[1];

        if($value_array[0] == 1){

            $errors[$key] = 'The '.$key.' field must be set';

        }

    }

}

function add_buddypress_activity($submission_title){

    global $current_user;

    if(function_exists('bp_activity_add')){

        $user_url = bp_core_get_user_domain($current_user -> ID);

        $user_name = bp_core_get_user_displayname($current_user -> ID);

        if($user_name == $current_user -> user_login){

            if(!empty($current_user -> nickname)){

                $user_name = $current_user -> nickname;

            } else if(!empty($current_user -> display_name)){

                $user_name = $current_user -> display_name;

            }

        }

        $user_link = '<a href="'.$user_url.'">'.$user_name.'</a>';

        $activity = array(
            'user_id'               => $current_user -> ID,
            'item_id'               => $current_user -> ID,
            'component'             => 'submissions',
            'primary_link'  => $user_url,
            'type'                  => 'submission_added',
            'action'                => $user_link.' submitted the manuscript "'.$submission_title.'"'
        );

        bp_activity_add($activity);

        return true;

    }

    return false;

}

function notify_via_email($transfer_array){

    notify_impulse_admin($transfer_array);

    notify_impulse_user($transfer_array);

}

function notify_impulse_admin($transfer_array){

    global $current_user, $theme_url;

//        wp_mail('bryan.owens@harpercollins.com', 'here' . $transfer_array['man_email'], 'goes' . $form_email);

    global $length_options, $genre_options, $subcategory_options, $period_options;

    $impulse_admin = array(
        //'email_address' => 'debug@gmail.com',
        'email_address' => 'impulse@harpercollins.com',
        'name'                  => 'Impulse'
    );

    $manuscript_link = make_manuscript_link($transfer_array['insert_id'], true);

    $subject_template = file_get_contents(ABSPATH.'wp-content/themes/avonromance/includes/impulse/template.admin_subject.txt');

    $body_template = file_get_contents(ABSPATH.'wp-content/themes/avonromance/includes/impulse/template.admin_body.txt');

    $replace_array = array(
        '%%%date%%%'                                    => date("Y-m-d H:i:s"),
        '%%%username%%%'                                => $transfer_array['man_name'],//$current_user -> user_login,
        '%%%email%%%'                                   => $_POST["man_email"],
        '%%%name%%%'                                    => $transfer_array['man_name'],//$current_user -> user_firstname.' '.$current_user -> user_lastname,
        '%%%man_title%%%'                               => $transfer_array['man_title'],
        '%%%manuscript_link%%%'                 => $manuscript_link,
        '%%%finished%%%'                                => $transfer_array['finished']?'yes':'no',
        '%%%published%%%'                               => $transfer_array['published']?'yes':'no',
        '%%%preferred_author_name%%%'   => $transfer_array['man_name'],
        '%%%length%%%'                                  => $length_options[$transfer_array['length']],
        '%%%genre%%%'                                   => $genre_options[$transfer_array['genre']],
        '%%%subcategory%%%'                             => $subcategory_options[$transfer_array['subcategory']],
        '%%%period%%%'                                  => $period_options[$transfer_array['period']],
        '%%%submission_request%%%'              => $transfer_array['submission_request'],
        '%%%synopsis%%%'                                => $transfer_array['synopsis'],
        '%%%scene%%%'                                   => $transfer_array['best_scene'],
        '%%%query_letter%%%'                    => $transfer_array['query_letter']
    );

    $subject = str_replace(array_keys($replace_array), array_values($replace_array), $subject_template);

    $body = str_replace(array_keys($replace_array), array_values($replace_array), $body_template);

    $submitter_subject = "Thank you for your manuscript submission!";
    $submitter_body = "Thank you for your submission to Avon Impulse. You can expect to hear back from us within eight to twelve weeks, however, due to the amount of submissions we receive, we are unable to respond personally to each query. Thank you again for your interest in Avon Impulse.";

    wp_mail($impulse_admin['email_address'], $subject, $body);
    wp_mail($_POST["man_email"], $submitter_subject, $submitter_body);

}

function make_manuscript_link($insert_id, $web_path = false){

    global $submission_target;

    if(!$web_path){

        return $submission_target.$insert_id.'.manuscript';

    }

    return plugins_url().'/my_submissions/view_submission.php?sid='.$insert_id.'&ahash='.SUBMISSIONS_ADMIN_HASH;

}

function notify_impulse_user($transfer_array){

    global $current_user;

    $subject_template = file_get_contents(ABSPATH.'wp-content/themes/avonromance/includes/impulse/template.user_subject.txt');

    $body_template = file_get_contents(ABSPATH.'wp-content/themes/avonromance/includes/impulse/template.user_body.txt');

    $replace_array = array(

    );

    $subject = str_replace(array_keys($replace_array), array_values($replace_array), $subject_template);

    $body = str_replace(array_keys($replace_array), array_values($replace_array), $body_template);

    wp_mail($current_user -> user_email, $subject, $body);

}

function get_file_type($file) {
    $dump = shell_exec(sprintf('file -bi %s', $file));
    $info = explode(' ', $dump);
    return chop($info[0]);
}

function upload_submission($insert_id){

    global $ok_filetypes;

    $extension = determine_file_extension();

    $target_path = make_manuscript_link($insert_id);

    if(!is_file($target_path)){

        // if(in_array($_FILES['manuscript_file']['type'], $ok_filetypes)){

        //        if(in_array(get_file_type($_FILES['manuscript_file']['tmp_name']), $ok_filetypes)){

        if(move_uploaded_file($_FILES['manuscript_file']['tmp_name'], $target_path.$extension)) {
            return true;
        }

        //    }else{

        //echo "Unacceptable filetype: ".$_FILES['manuscript_file']['type']."\n";

        //  }

    }

    return false;

}

function determine_file_extension(){

    $filename = $_FILES['manuscript_file']['name'];

    $matches = array();

    if(preg_match('/(\.[^\.]*)$/', $filename, $matches)){

        if(!empty($matches)){

            return $matches[0];

        }

    }

    return '.txt';


}

if(isset($_POST['wp_nonce'])){

    if(!wp_verify_nonce($transfer_array['wp_nonce'], $impulse_nonce)){
        $errors['security'] = 'The form you submitted contained a bad nonce value.  Please re-submit the form.  If this problem persists please contact the site admin.';

    }

    if(!is_user_logged_in()){

        $transfer_array['user_id'] = 9999; //$errors['login'] = 'You must be a logged-in in order to submit a manuscript.';
    }else{

        get_currentuserinfo();

        $transfer_array['user_id'] = sprintf('%d', $current_user -> ID);

    }

    if (filter_var($_POST['man_email'], FILTER_VALIDATE_EMAIL)) {

        $transfer_array['man_email'] = $_POST['man_email'];

    }else{

        $errors['security'] = 'You must supply a valid email.';

    }


    if(in_array($_FILES['manuscript_file']['error'], array('1','2'))){

        $errors['size'] = 'The file you uploaded exceeds the size limitations.  Please make sure your file is less than 3MB in size.';

    }else if($_FILES['manuscript_file']['error'] != '0'){

        $errors['size'] = 'The file upload was unsuccessful.  Please contact the site administrator.';

    }

    if(!empty($errors)){

        foreach($errors as $key => $value){

            $response_div .= '<li>'.$value.'</li>';

        }

        $response_div = '<ul class="impulse_errors">'.$response_div.'</ul>';

    }

    if(empty($response_div)){

        foreach($fields_whitelist as $key => $value_array){

            if(!in_array($key, $fields_blacklist)){

                $sub_values_array[$key] = $transfer_array[$key];

                $sub_values_types[] = $value_array[2];

            }

        }

        $sub_values_array['user_id'] = $transfer_array['user_id'];

        $sub_values_types[] = '%d';

        $sub_values_array['date'] = date("Y-m-d H:i:s", current_time('timestamp'));

        $sub_values_types[] = '%s';

        $insert_result = $wpdb -> insert(
            $submissions_table,
            $sub_values_array,
            $sub_values_types
        );

        if($insert_result){

            $transfer_array['insert_id'] = $wpdb -> insert_id;

            $file_upload_result = upload_submission($transfer_array['insert_id']);

            if($file_upload_result == true){

                notify_via_email($transfer_array);

                add_buddypress_activity($transfer_array['man_title']);

                $show_thankyou = true;

            }else{

                $wpdb -> query('
                                        DELETE FROM
                                                '.$submissions_table.'
                                        WHERE
                                                `id` = '.$transfer_array['insert_id']
                );

                $response_div = '<ul class="impulse_errors"><li>We are unable to process your upload. Submissions must be in .doc, .pdf or .rtf format.</li></ul>';

            }

        }else{

            $wpdb -> query('
                                DELETE FROM
                                        '.$submissions_table.'
                                WHERE
                                        `id` = '.$transfer_array['insert_id']
            );
            $response_div = '<ul class="impulse_errors"><li>We were unable to process your request.  Please contact the site administrator.</li></div>';

        }

    }

}

$overlay = '';

$nonce_value = wp_create_nonce($impulse_nonce);

foreach($transfer_array as $key => $value){

    $transfer_array[$key] = stripslashes($value);

}

?>
<div class="box impulse">
<div class="right_content">
    <div class="row">
        <?= file_get_contents(TEMPLATEPATH . "/ads/button_ad.php"); ?>
    </div>
</div>
<h3>Welcome to Avon Impulse</h3>
<div class="impulse_top_div">
    <div id="impulse_logo_container">
        <img src="<?php echo $theme_url; ?>/common/images/impulse.jpg" alt="Avon Impulse Logo"/>
    </div>
    <p>Romance readers know what's hot...in books, in technology, in trends.  Among the first to embrace books digitally, they have encouraged publishers to push the envelope editorially, exploring new subgenres and new formats.  With the evolved reader in mind, Avon is introducing a digital imprint, Avon Impulse.  This format will allow Avon to publish more quickly, with an eye to what's new in fiction and romance, delivering fresh, exciting content directly each month to the digital devices of today's savviest readers.</p>
    <p><a href="#faq"> Jump down to Frequently Asked Questions</a></p>
    <?php dynamic_sidebar('Impulse Body'); ?>
</div>
<h4 class="lightblue">Be a Writer for Impulse</h4>
<?= $response_div ?>
<?= $overlay ?>
<? if(!$show_thankyou){ ?>
<div class="impulse_form_div">
    <p>Fill out the fields to the best of your ability. <a href="<?php echo site_url(); ?>/avon-romance-submission-guidelines/" title="Submission Guidelines">Click here</a> for guidelines. We look forward to considering your submission!</p>
    <h4>Please complete the following form and make sure to hit submit</h4>
    <form name="impuse_form" id="impulse_form_id" enctype="multipart/form-data" action="<?php echo site_url(); ?>" method="POST">
        <ul>
            <li>
                <div class="error" id="man_title-error">The title must be set.</div>
                <div class="text_with_checkboxes">
                    <label for="title_id">Title of Manuscript</label>
                    <input type="text" class="text" name="man_title" id="man_title_id" value="<?= $transfer_array['man_title'] ?>"/>
                </div>
                <div class="error" id="finished-error"></div>
                <label for="finished_id">Is this manuscript finished?</label>
                <input type="radio" class="radio" checked="checked" name="finished" value="finished_yes" <?= $transfer_array['man_title'] == 1?'checked="checked"':'' ?>>Yes
                <input type="radio" class="radio" name="finished" value="finished_no" <?= ($transfer_array['man_title'] == 1)?'checked="checked"':'' ?>>No
            </li>
            <li>
                <div class="error" id="man_name-error">Your prefered author name must be set.</div>
                <div class="text_with_checkboxes">
                    <label for="name_id">Preferred Author Name</label>
                    <input type="text" class="text" name="man_name" id="man_name_id" value="<?= $transfer_array['man_name'] ?>"/>
                </div>
                <div class="error" id="published-error"></div>
                <label for="published_id">Have you published before?</label>
                <input type="radio" class="radio" checked="checked" name="published" value="published_yes" id="published_id" <?= $transfer_array['published'] == 1?'checked="checked"':'' ?>>Yes
                <input type="radio" class="radio" name="published" value="published_no" <?= ($transfer_array['published'] == 0)?'checked="checked"':'' ?>>No
            </li>
            <li>
                <label for="email_id">What is your email address?</label>
                <input type="text" class="text" name="man_email" id="man_email_id" value="<?= $transfer_array['man_email'] ?>"/>
            </li>
            <li>
                <div class="three_selects">
                    <div class="error" id="length-error">Please select a length category</div>
                    <label for="length_id">How long is the manuscript?</label>
                    <select name="length" class="select" id="length_id">
                        <?
                        foreach($length_options as $key => $value){

                            $selected = ($key == $transfer_array['length'])?'selected="selected"':'';

                            echo '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

                        }

                        ?>
                    </select>
                </div>
                <div class="three_selects">
                    <div class="error" id="period-error">Please select a period</div>
                    <label for="period_id">Time period?</label>
                    <select name="period" class="select" id="period_id">
                        <?
                        foreach($period_options as $key => $value){

                            $selected = ($key == $transfer_array['subcategory'])?'selected="selected"':'';

                            echo '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

                        }

                        ?>
                    </select>
                </div>
            </li>
            <li>
                <div class="three_selects">
                    <div class="error" id="genre-error">Please select a genre</div>
                    <label for="genre_id">Genre?</label>
                    <select name="genre" class="select" id="genre_id">
                        <?

                        foreach($genre_options as $key => $value){

                            $selected = ($key == $transfer_array['genre'])?'selected="selected"':'';

                            echo '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

                        }

                        ?>
                    </select>
                </div>
                <div class="three_selects">
                    <div class="error" id="subcategory-error">Please select a subcategory</div>
                    <label for="subcategory_id">Subcategory?</label>
                    <select name="subcategory" class="select" id="subcategory_id">
                        <?
                        foreach($subcategory_options as $key => $value){

                            $selected = ($key == $transfer_array['subcategory'])?'selected="selected"':'';

                            echo '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

                        }

                        ?>
                    </select>
                </div>
            </li>
            <li>
                <label for="submission_request_id">Is this in reference to a specific submission request Avon posted? If so, which one?</label>
                <textarea name="submission_request" id="submission_request_id"><?= $transfer_array['submission_request'] ?></textarea>
            </li>
            <li>
                <div class="error" id="synopsis-error">Please enter a brief synopsis</div>
                <label for="synopsis_id">Can you give us a brief synopsis? <span class="parenthetical">(less than 200 words)</span></label>
                <textarea name="synopsis" id="synopsis_id"><?= $transfer_array['synopsis'] ?></textarea>
            </li>
            <li>
                <div class="error" id="best_scene-error">Please post the best scene or the first 1000 words</div>
                <label for="best_scene_id">Post the best scene or the first 1000 words <span class="parenthetical">(less than 1000 words)</span></label>
                <textarea name="best_scene" id="best_scene_id"><?= $transfer_array['best_scene'] ?></textarea>
            </li>
            <li>
                <div class="error" id="query_letter-error">Please post your query letter</div>
                <label for="query_letter_id">Post your query letter <span class="parenthetical">(less than 750 words)</span></label>
                <textarea name="query_letter" id="query_letter_id"><?= $transfer_array['query_letter'] ?></textarea>
            </li>
            <li>
                <div class="error" id="manuscript_file-error">Upload a manuscript file</div>
                <label for="manuscript_file_id">Upload your manuscript</label>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= $max_file_size ?>" />
                <input type="file" class="file" name="manuscript_file" id="manuscript_file_id" />
            </li>
            <li>
                <input type="submit" class="submit" value="Submit Manuscript" />
                <input type="hidden" name="wp_nonce" value="<?= $nonce_value ?>" />
                <p>By clicking submit, I agree that I have read and understood the <a href="/terms-of-use/" target="_blank" >terms and conditions</a> and that HarperCollins has my permission to contact me regarding my submitted manuscript via email the the address provided at the time of registration to this site.</p>
            </li>
        </ul>
    </form>
</div>
    <?  }else{ ?>
<div class="impulse_thank_you">
    <h4 class="lightblue">Thank You for your submission!</h4>
    <p>Want to submit another?  <a href="<?php echo site_url(); ?>">Click here to reset the form.</a></p>
</div>
    <? } ?>
<hr/>
<a name="faq"></a>
<h4 class="lightblue">Frequently Asked Questions</h4>
<div class="impulse_faq_div">
    <ul>
        <li class="faq_question">MISSION STATEMENT: </li>
        <li class="faq_answer"> Avon Impulse is a digital imprint dedicated to publishing new e-books each month, rapidly delivering the best in romance fiction to today's e-savvy readers. </li>



        <li class="faq_question">Why is Avon embarking upon a digital publishing program? </li>
        <li class="faq_answer"> The retail market for books continues to evolve: shelf space is limited; romance readers have found a rich array of fresh content in using digital and eReading devices.  Romance is experiencing expansive growth in the e-book sector, and Avon is seizing this opportunity to grow both new and existing voices in fiction...and be first in delivering exactly what readers want to read. </li>

        <li class="faq_question">Why are you launching Avon Impulse now? </li>
        <li class="faq_answer"> Two reasons.  First, traditional channels for mass market genre fiction are shrinking.  Fewer grocery stores, drug stores, mall stores and superstores are carrying a broad selection of romance titles.  While there is a strong consumer market for Avon titles, the channels that we have always depended on to grow new voices and publish broadly are under pressure.  Second, the growth of eReaders and e-books have created a new opportunity that allows us to begin increasing the number and diversity of our romance list for the first time in 10 years. </li>

        <li class="faq_question">How is Avon Impulse different from Avon Books? </li>
        <li class="faq_answer"> Avon Impulse has grown organically from our existing publishing program.  We're always looking for ways to grow our authors in a marketplace rife with new opportunity.

        <li class="faq_answer"> This �e� imprint will enable Avon to react dynamically to the market: we can work faster to discern new trends as (or before) they emerge.  Avon Impulse titles will also have a more flexible publication schedule. Without traditional printing constraints, we can edit, market, and release e-books more quickly, allowing unprecedented speed to market.  We're also excited that the Avon Impulse imprint allows Avon such flexibility with the length of books we publish, as well as offers us greater scope in exploring new themes in romance. </li>

        <li class="faq_question">Why publish with Avon Impulse? </li>
        <li class="faq_answer"> Avon is an industry leader: our editorial staff and sales/marketing/publicity force are uniquely respected, and Avon Impulse authors benefit from those existing talents, platforms, and relationships.

            Avon's enthusiastic editorial team acquires content for both imprints. Both platforms offer similar benefits to authors: each Avon Impulse e-book will receive a distinctive cover treatment. During the publication cycle, the books will receive support from Avon's marketing and publicity professionals; and the e-books will benefit from our proven, strong relationships with all e-book channels and online retailers. </li>

        <li class="faq_question">How many titles per month will you release? </li>
        <li class="faq_answer"> Currently, we are looking to acquire enough content to release a new Avon Impulse title each week. </li>

        <li class="faq_question">Where will Avon Impulse titles be sold? </li>
        <li class="faq_answer"> Our Avon Impulse e-books will be available at every e-tailer, and readers will be able to download them onto every portable reading device sold today...and tomorrow, too. Readers who seek a hard copy of individual Avon Impulse titles will be able to lay their hands on physical books, thanks to a print-to-order option available through major online book retailers. </li>

        <li class="faq_question">Do Avon Impulse authors get an advance? What is the royalty rate? </li>
        <li class="faq_answer"> Avon Impulse will not pay an advance, but authors receive 25% royalties from the first book sold.  After an e-book sells 10,000 net copies, the author's royalty rate rises to 50%.  (Contracts will provide royalties for both e-book and print-to-order copies.) </li>

        <li class="faq_question">Will Avon Impulse e-books be distributed globally? </li>
        <li class="faq_answer"> Every Avon Impulse contract will include World English language distribution, so we can deliver these e-books everywhere around the world where English-language novels are sold. </li>

        <li class="faq_question">Will Avon Impulse titles be DRM protected? </li>
        <li class="faq_answer"> Yes.  Our retail partners will place DRM protection on Avon Impulse titles, following the standard procedure for all Avon books. </li>

        <li class="faq_question">For Avon Impulse, will you want agented or non-agented submissions? </li>
        <li class="faq_answer"> We will accept all submissions, agented and non-agented, for consideration. Please check our website (<a href="http://www.avonimpulse.com" title="Avon Impulse">http://www.avonimpulse.com</a>) for full details on our interactive submission process. </li>

        <li class="faq_question">What is the submission process? </li>
        <li class="faq_answer"> All non-agented manuscripts should be submitted at <a href="http://www.avonimpulse.com" title="Avon Impulse">http://www.avonimpulse.com</a>. Please note the detailed instructions on submission guidelines before sending your documents electronically. </li>

        <li class="faq_question">Where can I find the Submission Guidelines?
        <li class="faq_answer"> You can find our submission guidelines <a href="<?php echo site_url(); ?>/avon-romance-submission-guidelines/" title="Submission Guidelines">here</a>. </li>

        <li class="faq_question">Do I need to specify which imprint I am submitting to? </li>
        <li class="faq_answer"> Rest assured that when you submit your manuscript or proposal for consideration, our talented team of editors is appraising each project for publication on our Avon and Avon Impulse lists. They will carefully consider where each acquired project best fits. There is definite room for growth: Avon Impulse authors may also be considered for the traditional Avon Romance publishing program. </li>

        <li class="faq_question">What types of submissions is Avon Impulse interested in? </li>
        <li class="faq_answer"> We encourage creativity, so feel free to impress us with what you've got!  We also have our eye out for great submissions in the following subgenres: Contemporary, Fantasy, Futuristic, Ghost, Gothic, Historical, Magical, Time Travel, Western, Shifter,  Small Town, Steampunk, Suspense, Vampire (and others). </li>

        <li class="faq_question">Can existing Avon authors also publish to Avon Impulse? </li>
        <li class="faq_answer"> Of course! Our Avon authors are as excited about our new ePublishing platform as we are. </li>

        <li class="faq_question">If a debut author is published under the Avon Impulse imprint; is there a chance to be published in print as well? </li>
        <li class="faq_answer"> Yes, of course. Consumers will have the option to buy a printed copy of every Avon Impulse title.  These bound books will have a different cover price than the e-book, and will be available to order at online book retailers. </li>

        <li class="faq_question">Will my work be copyrighted? </li>
        <li class="faq_answer"> Each title receives individual copyright, retained by the author, as is the norm for all Avon titles. </li>

        <li class="faq_question">Is Avon Impulse publishing fiction only? </li>
        <li class="faq_answer"> Yes, at this time. </li>

        <li class="faq_question">Will manuscripts be edited, copyedited before going into print? </li>
        <li class="faq_answer"> Yes. Just as with our print titles, each Avon Impulse project will be assigned to an individual Avon editor, and will go through a comprehensive content and copyediting process. </li>

        <li class="faq_question">Will Avon Impulse titles benefit from Avon Publicity and Marketing? </li>
        <li class="faq_answer"> Yes. We will support Avon Impulse titles with comprehensive publicity/marketing campaigns, marketing each title, utilizing the digital landscape to strongly support this fantastic line of digital-first publications. </li>

        <li class="faq_question">What is Avon Impulse's five step marketing program? </li>
        <li class="faq_answer"> The categories follow:
            <ol>
                <li>Cross Promotion</li>
                <li>Digital Marketing</li>
                <li>Digital Publicity</li>
                <li>Interactive Assets</li>
                <li>Coaching</li>
            </ol>
        </li>

        <li class="faq_question">Will Avon Impulse e-book originals receive Galleys/eGalleys? </li>
        <li class="faq_answer"> Yes. Avon Impulse titles will receive eGalleys to help build buzz and excitement among key bloggers and reviewers prior to publication; we're also excited to send information about our e-book originals to media as these new titles go on-sale, for a second wave of promotion. </li>

        <li class="faq_question">Is Avon Impulse a Custom/Vanity Publisher? </li>
        <li class="faq_answer"> No. In acquiring for Avon Impulse, we carefully curate submissions and edit accepted manuscripts in the same fashion as all of our Avon projects. The line will benefit from Avon's editorial, marketing, publicity and sales platforms. And getting all these services at no cost to the author is the benefit of publishing with Avon, and Avon Impulse. </li>
    </ul>
</div>
<p><a href="#top">Jump back to top</a></p>
</div>