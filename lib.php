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
 * Token entropy in bytes
 */
define('HELPDESK_TOKEN_LENGTH', 32);

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
    $table = new stdClass;
    $table->width   = $width;
    $table->head    = array($string);
    print_table($table);
}

/**
 * This function is to easily determine if a user is capable or return the most
 * powerful capability the user has in the helpdesk.
 *
 * @param int   $capability Capability that is being checked.
 * @param bool  $require Makes the capability check a requirement to pass.
 * @return bool
 */
function helpdesk_is_capable($capability=null, $require=false, $user=null, $allow_external = false) {
    global $CFG;
    # check for external user
    $token = optional_param('token', '', PARAM_ALPHANUM);
    if ($allow_external and $CFG->block_helpdesk_external_user_tokens and strlen($token)) {
        $tid = required_param('id', PARAM_INT);
        if (!$watcher = get_record('block_helpdesk_watcher', 'token', $token, 'ticketid', $tid)) {
            return false;
        }
        if (!isset($capability)) {
            return HELPDESK_CAP_ASK;
        } else if ($capability == HELPDESK_CAP_ASK) {
            return true;
        }
        return false;
    }

    if (empty($user)) {
        global $USER;
        $user = $USER;
    }

    if (is_numeric($user)) {
        $user = get_record('user', 'id', $user);
    }

    $context = get_context_instance(CONTEXT_SYSTEM);
    if (empty($capability)) {
        // When returning which capability applies for the user, we can't
        // require this. The type that is returned is *mixed*.

        if ($require !== false) {
            notify(get_string('warning_getandrequire', 'block_helpdesk'));
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
    if (empty($userid)) {
        error(get_string('missingidparam', 'block_helpdesk'));
    }
    static $users = array();
    if (isset($users[$userid])) {
        return $users[$userid];
    }

    global $CFG;

    $sql = "
        SELECT hu.id AS hd_userid, u.id AS userid, u.firstname, u.lastname, u.email,
            COALESCE(u.phone1, u.phone2) AS phone
        FROM {$CFG->prefix}user AS u
        LEFT JOIN {$CFG->prefix}block_helpdesk_hd_user AS hu ON hu.userid = u.id
        WHERE u.id = $userid
    ";
    $user = get_record_sql($sql);
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
    $hd_user = get_record('block_helpdesk_hd_user', 'userid', $userid);
    if (!$hd_user) {
        return helpdesk_create_hd_user($userid);
    }
    return $hd_user->id;
}

/**
 * Makes an hd_user record for a local Moodle user
 */
function helpdesk_create_hd_user($userid) {
    $hd_user = (object) array(
        'userid' => $userid
    );
    return insert_record('block_helpdesk_hd_user', $hd_user);
}

function helpdesk_get_hd_user($hd_userid) {
    if (empty($hd_userid)) {
        error(get_string('missingidparam', 'block_helpdesk'));
    }
    static $users = array();
    if (isset($users[$hd_userid])) {
        return $users[$hd_userid];
    }

    global $CFG;

    $sql = "
        SELECT hu.id AS hd_userid, u.id AS userid, COALESCE(u.email, hu.email) AS email,
            COALESCE(u.firstname, hu.name) AS firstname, COALESCE(u.lastname, '') AS lastname,
            COALESCE(u.phone1, u.phone2, hu.phone) AS phone
        FROM {$CFG->prefix}block_helpdesk_hd_user AS hu
        LEFT JOIN {$CFG->prefix}user AS u ON u.id = hu.userid
        WHERE hu.id = $hd_userid
    ";
    if (!$user = get_record_sql($sql)) {
        return false;
    }
    $users[$hd_userid] = $user;
    return $user;
}

function helpdesk_user_link($user) {
    global $CFG;
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
    $url = $url->out();
    return "<a href=\"$url\">" . fullname($user) . "</a> $type";
}

function helpdesk_generate_token() {
    return bin2hex(openssl_random_pseudo_bytes(HELPDESK_TOKEN_LENGTH));
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
    global $CFG;
    $result = helpbutton($name, $title, 'block_helpdesk', true, false, '', $return);
    return $result;

}

/**
 * This is a wrapper function for Moodle's build header. This moodle function
 * gets called *a lot* so if anything changes it should be in one place.
 * Besides, the header is going to be very similar from one page to another with
 * the exception of navigation.
 *
 * @param object        $nav will be a navigation build by build_navigation().
 * @return null
 */
function helpdesk_print_header($nav, $title=null) {
    global $CFG, $COURSE;
    $helpdeskstr = get_string('helpdesk', 'block_helpdesk');
    if (empty($title)) {
	$title = $helpdeskstr;
    }
    $meta = "<meta http-equiv=\"x-ua-compatible\" content=\"IE=8\" />\n
	     <link rel=\"stylesheet\" type=\"text/css\" href=\"$CFG->wwwroot/blocks/helpdesk/style.css\" />\n";
    print_header($title, $helpdeskstr, $nav, '', $meta);
}
