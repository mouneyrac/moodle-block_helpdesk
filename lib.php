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
        return fullname($user) . " $type";
    }
    return "<a href=\"{$url->out()}\">" . fullname($user) . "</a> $type";
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
        echo "<div class='external'>" . get_string('welcome', 'block_helpdesk') . fullname($USER) . "</div>";
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
    send_stored_file($file, 0, 0, true);
}