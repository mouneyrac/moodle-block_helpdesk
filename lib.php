<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This is the core helpdesk library. This contains the building blocks of the
 * entire helpdesk.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author	Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

$hdpath = "$CFG->dirroot/blocks/helpdesk";
require_once("$hdpath/db/access.php");
require_once("$hdpath/helpdesk.php");
require_once("$hdpath/helpdesk_ticket.php");
unset($hdpath);

define('HELPDESK_DATE_FORMAT', 'F j, Y, g:i a');

/**
 * These are update types that define how the update was made, and will allow
 * the user to filter what they do not need to see (such as system updates, and
 * extra updates that show when users were assigned and such.)
 */

/**
 * This type is an update created by a real user.
 */
define('HELPDESK_UPDATE_TYPE_USER', 'update_type_user');

/**
 * This is an update created from a user action, such as assigned a user, or
 * adding a tag.
 */
define('HELPDESK_UPDATE_TYPE_DETAILED', 'update_type_detailed');

/**
 * This is an update created by the system, most likely from a cron job or some
 * sort of equivalent system update.
 */
define('HELPDESK_UPDATE_TYPE_SYSTEM', 'update_type_system');

/**
 * Token stuff
 */
define('HELPDESK_TOKEN_LENGTH', 32);        # entropy in bytes
define('HELPDESK_DEFAULT_TOKEN_EXP', 7);    # default token expiration in days

/**
 * Helpdesk userlist functions
 */
define('HELPDESK_USERLIST_NEW_SUBMITTER', 'newsubmitter');
define('HELPDESK_USERLIST_NEW_WATCHER', 'newwatcher');
define('HELPDESK_USERLIST_MANAGE_EXTERNAL', 'manageext');
define('HELPDESK_USERLIST_SUBMIT_AS', 'submitas');
define('HELPDESK_USERLIST_PLUGIN', 'plugin');

/**
 * Helpdesk userlist user sets
 */
define('HELPDESK_USERSET_ALL', 'all');
define('HELPDESK_USERSET_INTERNAL', 'internal');
define('HELPDESK_USERSET_EXTERNAL', 'external');

/**
 * Gets a speicificly formatted date string for the helpdesk block.
 *
 * @param int   $date is the unix time to be converted to readable string.
 * @return string
 */
function helpdesk_get_date_string($date) {
    return userdate($date);
}

function print_table_head($string, $width='95%') {
    $table = new html_table();
    $table->width   = $width;
    $table->head    = array($string);
    echo html_writer::table($table);
}

/**
 * This function is to easily determine if a user is capable or return the most
 * powerful capability the user has in the helpdesk.
 *
 * @param int   $capability Capability that is being checked.
 * @param bool  $require Makes the capability check a requirement to pass.
 * @return bool
 */
function helpdesk_is_capable($capability=null, $require=false, $user=null) {
    global $USER, $DB, $OUTPUT;

    if (empty($user)) {
        $user = $USER;
    }

    if (!empty($user->helpdesk_external)) {    # check for external user
        if (!isset($capability)) {
            return HELPDESK_CAP_ASK;
        } else if ($capability == HELPDESK_CAP_ASK) {
            return true;
        }
        return false;
    }

    if (is_numeric($user)) {
        $user = $DB->get_record('user', array('id' => $user));
    }

    $context = context_system::instance();
    if (empty($capability)) {
        // When returning which capability applies for the user, we can't
        // require this. The type that is returned is *mixed*.

        if ($require !== false) {
            echo $OUTPUT->notification(get_string('warning_getandrequire', 'block_helpdesk'));
        }

        // Order here does matter.
        $rval = false;
        $cap = has_capability(HELPDESK_CAP_ASK, $context, $user->id);
        if ($cap) {
            $rval = HELPDESK_CAP_ASK;
        }
        $cap = has_capability(HELPDESK_CAP_ANSWER, $context, $user->id);
        if ($cap) {
            $rval = HELPDESK_CAP_ANSWER;
        }
        return $rval;
    }

    if ($require) {
        require_capability($capability, $context, $user->id);
        return true;
    }
    return has_capability($capability, $context, $user->id);
}

/**
 * This function gets a specific user in Moodle. Returns false if no user has
 * a matching ID, or returns a record from the database.
 *
 * @return mixed
 */
function helpdesk_get_user($userid) {
    global $DB;

    if (empty($userid)) {
        print_error('missingidparam', 'block_helpdesk');
    }
    static $users = array();
    if (isset($users[$userid])) {
        return $users[$userid];
    }

    $sql = "
        SELECT hu.id AS hd_userid, u.id AS userid, u.firstname, u.lastname, u.email,
            COALESCE(u.phone1, u.phone2) AS phone
        FROM {user} AS u
        LEFT JOIN {block_helpdesk_hd_user} AS hu ON hu.userid = u.id
        WHERE u.id = $userid
    ";
    $user = $DB->get_record_sql($sql);
    if (!$user) {
        return false;
    }
    if (!isset($user->hd_userid)) {
        # make an hd_user record for the user
        $user->hd_userid = helpdesk_create_hd_user($userid);
    }
    $users[$userid] = $user;
    return $user;
}

function helpdesk_ensure_hd_user($userid) {
    global $DB;

    $hd_user = $DB->get_record('block_helpdesk_hd_user', array('userid' => $userid));
    if (!$hd_user) {
        return helpdesk_create_hd_user($userid);
    }
    return $hd_user->id;
}

/**
 * Makes an hd_user record for a local Moodle user
 */
function helpdesk_create_hd_user($userid) {
    global $DB;

    $hd_user = (object) array(
        'userid' => $userid
    );
    return $DB->insert_record('block_helpdesk_hd_user', $hd_user);
}

function helpdesk_get_hd_user($hd_userid) {
    global $DB;

    if (empty($hd_userid)) {
        print_error('missingidparam', 'block_helpdesk');
    }
    static $users = array();
    if (isset($users[$hd_userid])) {
        return $users[$hd_userid];
    }

    $sql = "
        SELECT hu.id AS hd_userid, u.id AS userid, COALESCE(u.email, hu.email) AS email,
            COALESCE(u.firstname, hu.name) AS firstname, COALESCE(u.lastname, '') AS lastname,
            COALESCE(u.phone1, u.phone2, hu.phone) AS phone, hu.type
        FROM {block_helpdesk_hd_user} AS hu
        LEFT JOIN {user} AS u ON u.id = hu.userid
        WHERE hu.id = $hd_userid
    ";
    if (!$user = $DB->get_record_sql($sql)) {
        return false;
    }
    $users[$hd_userid] = $user;
    return $user;
}

function helpdesk_user_link($user) {
    global $CFG, $USER;
    $type = '';
    if (isset($user->userid)) {
        $url = new moodle_url("$CFG->wwwroot/user/view.php");
        $url->param('id', $user->userid);
    } else {
        $url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/userlist.php");
        $url->param('hd_userid', $user->hd_userid);
        $url->param('function', HELPDESK_USERLIST_MANAGE_EXTERNAL);
        $type = get_string('nonmoodleuser', 'block_helpdesk');
    }
    if (!empty($USER->helpdesk_external)) {
        return fullname_nowarnings($user) . " $type";
    }
    return "<a href=\"{$url->out()}\">" . fullname_nowarnings($user) . "</a> $type";
}

function helpdesk_generate_token() {
    return bin2hex(openssl_random_pseudo_bytes(HELPDESK_TOKEN_LENGTH));
}

function helpdesk_authenticate_token($ticketid, $token) {
    global $CFG, $DB, $USER;
    if (empty($CFG->block_helpdesk_external_user_tokens)) {
        print_error('invalidtoken', 'block_helpdesk');
    }
    if (!$watcher = $DB->get_record('block_helpdesk_watcher', array('token' => $token, 'ticketid' => $ticketid))) {
        print_error('invalidtoken', 'block_helpdesk');
    }

    if (!isset($CFG->block_helpdesk_token_exp)) {
        $token_exp = HELPDESK_DEFAULT_TOKEN_EXP;
    } else {
        $token_exp = $CFG->block_helpdesk_token_exp;
    }
    if ($token_exp) {   # non-zero (zero is forever)
        $token_exp = $token_exp * 24 * 60 * 60;                 # days to seconds

        /**
         * echo "token_exp " . $token_exp . "</br >";
         * echo "last_issued " . $watcher->token_last_issued . "</br >";
         * echo "time " . time() . "</br >";
         * echo "li+exp " . ($watcher->token_last_issued + $token_exp) . "</br >";
         * echo "li+exp-time " . ($watcher->token_last_issued + $token_exp - time());
         */

        if (($watcher->token_last_issued + $token_exp) < time()) {
            print_error('invalidtoken', 'block_helpdesk');
        }
    }

    $USER = helpdesk_get_hd_user($watcher->hd_userid);
    $USER->ignoresesskey = true;
    $USER->helpdesk_external = true;
    $USER->helpdesk_token = $token;
    $USER->id = 0;

    return;
}

/**
 * This function gets a specific variable out of the global session variable,
 * but only in the help desk object. It can return any number of things, or null
 * if nothing is there.
 *
 * @param string        $varname Name of the helpdesk session variable.
 * @return mixed
 */
function helpdesk_get_session_var($varname) {
    global $SESSION;
    if (isset($SESSION->block_helpdesk)) {
        return isset($SESSION->block_helpdesk->$varname) ?
	       $SESSION->block_helpdesk->$varname : false;
    }
    $SESSION->block_helpdesk = new stdClass;
    return null;
}

/**
 * This function sets a specific attribute in the global session variable in the
 * helpdesk object. It will return a bool depending on outcome.
 *
 * @param string        $varname is the attribute's name
 * @param string        $value is the value to set.
 * @return bool
 */
function helpdesk_set_session_var($varname, $value) {
    global $SESSION;
    if (!isset($SESSION->block_helpdesk)) {
        $SESSION->block_helpdesk = new stdClass;
    }
    $SESSION->block_helpdesk->$varname = $value;
}

/**
 * Wrapper for helpbutton to make the call more simple. Always returns the HTML
 * for the help button.
 *
 * @param string        $title is the alt text/title for this help button.
 * @param string        $text is the text the user will see if they click on it.
 * @return string
 */
function helpdesk_simple_helpbutton($title, $name, $return=true) {
    global $OUTPUT;
    $result = $OUTPUT->help_icon($name, 'block_helpdesk', false);
    return $result;

}

/**
 * This is a wrapper function for Moodle's build header. This moodle function
 * gets called *a lot* so if anything changes it should be in one place.
 * Besides, the header is going to be very similar from one page to another with
 * the exception of navigation.
 *
 * @param array     $nav will be a build_navigation() input array.
 * @return null
 */
function helpdesk_print_header($nav, $title=null) {
    global $CFG, $USER, $OUTPUT, $PAGE, $DB;

    $meta = "<meta http-equiv=\"x-ua-compatible\" content=\"IE=8\" />\n
	     <link rel=\"stylesheet\" type=\"text/css\" href=\"$CFG->wwwroot/blocks/helpdesk/style.css\" />\n";

    if (!empty($USER->helpdesk_external)) {
        $PAGE->set_title('');
        $PAGE->set_heading('');
        $PAGE->set_focuscontrol('');
        echo $OUTPUT->header();
        echo "<div class='external'>" . get_string('welcome', 'block_helpdesk') . fullname_nowarnings($USER) . "</div>";
        echo $OUTPUT->heading($DB->get_record('course', array('id' => SITEID))->fullname . ' ' . get_string('helpdesk', 'block_helpdesk'), 1);
        return;
    }

    $helpdeskstr = get_string('helpdesk', 'block_helpdesk');
    if (empty($title)) {
        $title = $helpdeskstr;
    }
    $PAGE->set_title($title);
    $PAGE->set_heading($helpdeskstr);
    $PAGE->set_focuscontrol('');
    echo $OUTPUT->header();
}

function helpdesk_print_footer() {
    global $USER, $OUTPUT;
    if (empty($USER->helpdesk_external)) {
        echo $OUTPUT->footer();
    }
}

/**
 * Serves the helpdesk files.
 *
 * @package  block_helpdesk
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function block_helpdesk_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $USER;

    // The context is the system.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // User must be logged in.
    // Note: if ever the block_helpdesk_email_htmlcontent admin setting comes to support files,
    //       then you will need to allow public access to its specific filearea.
    require_login();

    // User must have the helpdesk view capability.
    if (!has_capability('block/helpdesk:view', $context)) {
        return false;
    }

    // We are going to check if the user is allowed to view the ticket/notes/tag.
    $itemid = (int)array_shift($args);
    $tablesuffix = '';
    switch ($filearea) {
        case 'ticketnotes':
            $tablesuffix = '_update';
            break;
        case 'tickettag':
            $tablesuffix = '_tag';
            break;
        case 'ticketdetail':
            break;
    }

    // Check that the item exists.
    if (!($item = $DB->get_record('block_helpdesk_ticket' . $tablesuffix, array('id'=>$itemid)))) {
        return false;
    }

    // Check if the user is admin.
    if (!is_siteadmin()) {
        // Check if the user is the creator.
        switch ($filearea) {
            case 'ticketnotes':
            case 'tickettag':
                $creatorid = $DB->get_field('block_helpdesk_ticket', 'userid', array('id' => $item->ticketid));
                break;
            case 'ticketdetail':
                $creatorid = $itemid;
                break;
        }
        if ($USER->id !== $creatorid) {
            // Check if the user has the capability to answer.
            if (!has_capability('HELPDESK_CAP_ANSWER', $context)) {
                // The user has no permissions to download the file.
                return false;
            }
        }
    }

    // Check the file exists in the moodledata folder.
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/block_helpdesk/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    // for folder module, we force download file all the time
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Evil function that eats the fullname() warnings because we can't bother to rewrite every single
 * user requests to include all missing name fields, and we are annoyed by these name warnings in debug mode :P
 * @param $user
 * $return string fullname
 */
function fullname_nowarnings($user) {
    global $CFG;

    // As except this case, Moodle 2.5 is the exact same version than 2.6/2.7,
    // in order to avoid to create a branch specific to 2.5 for this "hack" ,let's check the Moodle version.
    if ($CFG->version >= 2013111800) {
        $debugdeveloper_backup = $CFG->debugdeveloper;
        $CFG->debugdeveloper = false;
        $fullname = fullname($user);
        $CFG->debugdeveloper = $debugdeveloper_backup;
    } else {
        // Moodle version earlier than to 2.6.
        $fullname = fullname($user);
    }
    return $fullname;
}

/**
 * IN ORDER TO MAKE THE MAINTENANCE EASY THIS FUNCTION IS THE EXACT COPY OF
 * THE CORE FUNCTION EXCEPT WHEN COMMENTED WITH:
 *
 * ////////// CORE CHANGES
 * //
 * ////////// END OF CORE CHANGES
 *
 * Send an email to a specified user
 *
 * @param stdClass $user  A {@link $USER} object
 * @param stdClass $from A {@link $USER} object
 * @param string $subject plain text subject line of the email
 * @param string $messagetext plain text version of the message
 * @param string $messagehtml complete html version of the message (optional)
 * @param string $attachment a file on the filesystem, relative to $CFG->dataroot
 * @param string $attachname the name of the file (extension indicates MIME)
 * @param bool $usetrueaddress determines whether $from email address should
 *          be sent out. Will be overruled by user profile setting for maildisplay
 * @param string $replyto Email address to reply to
 * @param string $replytoname Name of reply to recipient
 * @param int $wordwrapwidth custom word wrap width, default 79
 * @return bool Returns true if mail was sent OK and false if there was an error.
 */
function email_to_external_user($user, $from, $subject, $messagetext, $messagehtml = '', $attachment = '', $attachname = '',
                                $usetrueaddress = true, $replyto = '', $replytoname = '', $wordwrapwidth = 79) {

    global $CFG;

    if (empty($user) || empty($user->email)) {
        $nulluser = 'User is null or has no email';
        error_log($nulluser);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$nulluser);
        }
        return false;
    }

    if (!empty($user->deleted)) {
        // do not mail deleted users
        $userdeleted = 'User is deleted';
        error_log($userdeleted);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$userdeleted);
        }
        return false;
    }

    if (!empty($CFG->noemailever)) {
        // hidden setting for development sites, set in config.php if needed
        $noemail = 'Not sending email due to noemailever config setting';
        error_log($noemail);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$noemail);
        }
        return true;
    }

    if (!empty($CFG->divertallemailsto)) {
        $subject = "[DIVERTED {$user->email}] $subject";
        $user = clone($user);
        $user->email = $CFG->divertallemailsto;
    }

    // skip mail to suspended users
    if ((isset($user->auth) && $user->auth=='nologin') or (isset($user->suspended) && $user->suspended)) {
        return true;
    }

    if (!validate_email($user->email)) {
        // we can not send emails to invalid addresses - it might create security issue or confuse the mailer
        $invalidemail = "User $user->id (".fullname($user).") email ($user->email) is invalid! Not sending.";
        error_log($invalidemail);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$invalidemail);
        }
        return false;
    }

    if (over_bounce_threshold($user)) {
        $bouncemsg = "User $user->id (".fullname($user).") is over bounce threshold! Not sending.";
        error_log($bouncemsg);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$bouncemsg);
        }
        return false;
    }

    // If the user is a remote mnet user, parse the email text for URL to the
    // wwwroot and modify the url to direct the user's browser to login at their
    // home site (identity provider - idp) before hitting the link itself
    if (is_mnet_remote_user($user)) {
        require_once($CFG->dirroot.'/mnet/lib.php');

        $jumpurl = mnet_get_idp_jump_url($user);
        $callback = partial('mnet_sso_apply_indirection', $jumpurl);

        $messagetext = preg_replace_callback("%($CFG->wwwroot[^[:space:]]*)%",
            $callback,
            $messagetext);
        $messagehtml = preg_replace_callback("%href=[\"'`]($CFG->wwwroot[\w_:\?=#&@/;.~-]*)[\"'`]%",
            $callback,
            $messagehtml);
    }
    $mail = get_mailer();

    if (!empty($mail->SMTPDebug)) {
        echo '<pre>' . "\n";
    }

    $temprecipients = array();
    $tempreplyto = array();

    $supportuser = generate_email_supportuser();

    // make up an email address for handling bounces
    if (!empty($CFG->handlebounces)) {
        $modargs = 'B'.base64_encode(pack('V',$user->id)).substr(md5($user->email),0,16);
        $mail->Sender = generate_email_processing_address(0,$modargs);
    } else {
        $mail->Sender = $supportuser->email;
    }

    if (is_string($from)) { // So we can pass whatever we want if there is need
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = $from;
    } else if ($usetrueaddress and $from->maildisplay) {
        $mail->From     = $from->email;
        $mail->FromName = fullname($from);
    } else {
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = fullname($from);
        if (empty($replyto)) {
            $tempreplyto[] = array($CFG->noreplyaddress, get_string('noreplyname'));
        }
    }

    if (!empty($replyto)) {
        $tempreplyto[] = array($replyto, $replytoname);
    }

    $mail->Subject = substr($subject, 0, 900);

    $temprecipients[] = array($user->email, fullname($user));

    $mail->WordWrap = $wordwrapwidth;                   // set word wrap

    if (!empty($from->customheaders)) {                 // Add custom headers
        if (is_array($from->customheaders)) {
            foreach ($from->customheaders as $customheader) {
                $mail->AddCustomHeader($customheader);
            }
        } else {
            $mail->AddCustomHeader($from->customheaders);
        }
    }

    if (!empty($from->priority)) {
        $mail->Priority = $from->priority;
    }

    if ($messagehtml && !empty($user->mailformat) && $user->mailformat == 1) { // Don't ever send HTML to users who don't want it
        $mail->IsHTML(true);
        $mail->Encoding = 'quoted-printable';           // Encoding to use
        $mail->Body    =  $messagehtml;
        $mail->AltBody =  "\n$messagetext\n";
    } else {
        $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";
    }

    if ($attachment && $attachname) {
        if (preg_match( "~\\.\\.~" ,$attachment )) {    // Security check for ".." in dir path
            $temprecipients[] = array($supportuser->email, fullname($supportuser, true));
            $mail->AddStringAttachment('Error in attachment.  User attempted to attach a filename with a unsafe name.', 'error.txt', '8bit', 'text/plain');
        } else {
            require_once($CFG->libdir.'/filelib.php');
            $mimetype = mimeinfo('type', $attachname);
            $mail->AddAttachment($CFG->dataroot .'/'. $attachment, $attachname, 'base64', $mimetype);
        }
    }

    // Check if the email should be sent in an other charset then the default UTF-8
    if ((!empty($CFG->sitemailcharset) || !empty($CFG->allowusermailcharset))) {

        // use the defined site mail charset or eventually the one preferred by the recipient
        $charset = $CFG->sitemailcharset;
        if (!empty($CFG->allowusermailcharset)) {
            if ($useremailcharset = get_user_preferences('mailcharset', '0', $user->id)) {
                $charset = $useremailcharset;
            }
        }

        // convert all the necessary strings if the charset is supported
        $charsets = get_list_of_charsets();
        unset($charsets['UTF-8']);
        if (in_array($charset, $charsets)) {
            $mail->CharSet  = $charset;
            $mail->FromName = textlib::convert($mail->FromName, 'utf-8', strtolower($charset));
            $mail->Subject  = textlib::convert($mail->Subject, 'utf-8', strtolower($charset));
            $mail->Body     = textlib::convert($mail->Body, 'utf-8', strtolower($charset));
            $mail->AltBody  = textlib::convert($mail->AltBody, 'utf-8', strtolower($charset));

            foreach ($temprecipients as $key => $values) {
                $temprecipients[$key][1] = textlib::convert($values[1], 'utf-8', strtolower($charset));
            }
            foreach ($tempreplyto as $key => $values) {
                $tempreplyto[$key][1] = textlib::convert($values[1], 'utf-8', strtolower($charset));
            }
        }
    }

    foreach ($temprecipients as $values) {
        $mail->AddAddress($values[0], $values[1]);
    }
    foreach ($tempreplyto as $values) {
        $mail->AddReplyTo($values[0], $values[1]);
    }

    if ($mail->Send()) {

        ////////// CORE CHANGES
        // set_send_count($user);
        ////////// END OF CORE CHANGES
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return true;
    } else {
        add_to_log(SITEID, 'library', 'mailer', qualified_me(), 'ERROR: '. $mail->ErrorInfo);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$mail->ErrorInfo);
        }
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return false;
    }

}