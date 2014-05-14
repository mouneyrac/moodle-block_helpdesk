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
 * This script handles the updating of tickets by managing the UI and entry
 * level functions for the task.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Joanthan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once(dirname(__FILE__) . '/usersearch_form.php');
require_once(dirname(__FILE__) . '/user_form.php');

require_login(0, false);

$function       = required_param('function', PARAM_ALPHA);
$returnurl      = optional_param('returnurl', '', PARAM_RAW);
$paramname      = optional_param('paramname', '', PARAM_TEXT);
$ticketid       = optional_param('tid', null, PARAM_INT);
$userset        = optional_param('userset', null, PARAM_ALPHA);
$hd_userid      = optional_param('hd_userid', 0, PARAM_INT);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 20, PARAM_INT);
$search         = optional_param('search', '', PARAM_TEXT);

$thisurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/userlist.php");
$thisurl->param('function', $function);
$searchurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$nav = array (
    array (
        'name' => get_string('helpdesk', 'block_helpdesk'),
        'link' => $searchurl->out()
    )
);
if (!empty($ticketid)) {
    $ticketreturn = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
    $ticketreturn->param('id', $ticketid);
    $nav[] = array (
        'name' => get_string('ticketview', 'block_helpdesk'),
        'link' => $ticketreturn->out()
    );
}

$title = get_string('helpdeskselectuser', 'block_helpdesk');
$selecttext = get_string('selectuser', 'block_helpdesk');
if (!isset($userset)) {
    if ($CFG->block_helpdesk_allow_external_users) {
        $userset = HELPDESK_USERSET_ALL;
    } else {
        $userset = HELPDESK_USERSET_INTERNAL;
    }
}
switch ($function) {
case HELPDESK_USERLIST_NEW_SUBMITTER:
    $thisurl->param('tid', $ticketid);
    $returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/edit.php");
    $returnurl->param('id', $ticketid);
    $paramname = 'newuser';

    $nav[] = array (
        'name' => get_string('updateticketoverview', 'block_helpdesk'),
        'link' => $returnurl->out()
    );
    $nav[] = array (
        'name' => get_string('changesubmitter', 'block_helpdesk'),
    );
    $title = get_string('helpdeskchangeuser', 'block_helpdesk');
    break;
case HELPDESK_USERLIST_NEW_WATCHER:
    $thisurl->param('tid', $ticketid);
    $returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/manage_watchers.php");
    $returnurl->param('tid', $ticketid);
    $paramname = 'hd_userid';

    $nav[] = array(
        'name' => get_string('addwatcher', 'block_helpdesk'),
    );
    $title = get_string('helpdeskselectwatcher', 'block_helpdesk');
    break;
case HELPDESK_USERLIST_MANAGE_EXTERNAL:
    if (!$CFG->block_helpdesk_allow_external_users) {
        print_error('externalusersdisabled', 'block_helpdesk');
    }
    $userset = HELPDESK_USERSET_EXTERNAL;
    $returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/hduser.php");
    $returnurl->param('returnurl', $thisurl->out());
    $paramname = 'id';
    $nav[] = array(
        'name' => get_string('manageexternal', 'block_helpdesk'),
    );
    $title = get_string('helpdeskmanageexternal', 'block_helpdesk');
    $selecttext = get_string('edituser', 'block_helpdesk');
    break;
case HELPDESK_USERLIST_SUBMIT_AS:
    $returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/new.php");
    $paramname = 'hd_userid';
    $nav[] = array(
        'name' => get_string('helpdesknewticket', 'block_helpdesk'),
    );
    $title = get_string('helpdeskselectticketuser', 'block_helpdesk');
    break;
case HELPDESK_USERLIST_PLUGIN:
    $thisurl->param('returnurl', $returnurl);
    $returnurl = new moodle_url($returnurl);
    break;
default:
    print_error('unknownfunction');
}
$nav[] = array (
    'name' => get_string('selectuser', 'block_helpdesk'),
);

$url = new moodle_url("{$CFG->wwwroot}/blocks/helpdesk/userlist.php");
//helpdesk_print_header($nav);
helpdesk::page_init($title, $nav);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);

echo "<div class=\"left2div\">";

// search box
$search_form = new helpdesk_usersearch_form($userset);
$search_form->set_data((object) array(
    'search' => $search,
    'function' => $function,
    'returnurl' => $returnurl->out(),
    'paramname' => $paramname,
    'tid' => $ticketid,
//    'userid' => $userid,
));
$search_form->display();

echo "</div> <div class=\"right2div\">";

// new user
if ($CFG->block_helpdesk_allow_external_users and !$hd_userid) {
    $user_form = new helpdesk_user_form(true, "$CFG->wwwroot/blocks/helpdesk/hduser.php");
    $user_form->set_data((object) array(
        'returnurl' => $function == HELPDESK_USERLIST_MANAGE_EXTERNAL ? $thisurl->out() : $returnurl->out(),
        'paramname' => $paramname,
        'ticketid' => $ticketid,
    ));
    $user_form->display();
}

echo "</div><div class=\"clear\">";

// search results

$columns = array (
    'fullname' => '',
    'email' => '',
    'phone' => '',
    'usertype' => 'block_helpdesk',
);
$table = new html_table();
$table->head = array();
$table->data = array();
$table->attributes = array('class' => 'generaltable helpdesktable');

foreach ($columns as $column => $module) {
    if ($column == '') {
        $table->head[] = '';
        continue;
    }
    $table->head[$column] = get_string($column, $module);
}

if ($function == HELPDESK_USERLIST_MANAGE_EXTERNAL and $hd_userid) {
    $search_count = 1;
    $users = array(helpdesk_get_hd_user($hd_userid));
} else {
    $sql_users = "
        FROM {user} AS u
        LEFT JOIN {block_helpdesk_hd_user} AS hu2 ON hu2.userid = u.id
        WHERE u.deleted = 0
    ";
    $sql_hdusers = "
        FROM {block_helpdesk_hd_user} AS hu
        WHERE userid IS NULL
    ";
    // We create a the $sqlparams here as there is a end sql request that use all parameters (legacy from 1.9).
    $sqlparams = array();
    $search_count = $full_count = 0;
    if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_INTERNAL) {
        $full_count += $DB->count_records_sql('SELECT COUNT(*)' . $sql_users);
    }
    if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_EXTERNAL) {
        $full_count += $DB->count_records_sql('SELECT COUNT(*)' . $sql_hdusers);
    }
    if ($search) {
        $param_count = 0;
        $sql_search = explode(' ', $search);
        foreach ($sql_search as $k => $v) {
            if (!strlen($v)) { unset($sql_search[$k]); }
            $sql_search[$k] = "%$v%";
        }
        // $i is a unique id for the sql named parameters.
        $i = 0;
        if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_INTERNAL) {
            foreach ($sql_search as $v) {
                $i = $i + 1;
                $sql_users .= "
                    AND (". $DB->sql_like('u.username', ':username' . $i, false) . "
                    OR " . $DB->sql_like('u.firstname', ':firstname' . $i, false) . "
                    OR " . $DB->sql_like('u.lastname', ':lastname' . $i, false) .  "
                    OR " . $DB->sql_like('u.email', ':email' . $i, false) . ")";
                $sqlparams['username' . $i] = $v;
                $sqlparams['firstname' . $i] = $v;
                $sqlparams['lastname' . $i] = $v;
                $sqlparams['email' . $i] = $v;
            }
            $search_count += $DB->count_records_sql('SELECT COUNT(*)' . $sql_users, $sqlparams);
        }
        if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_EXTERNAL) {
            foreach ($sql_search as $v) {
                $i = $i + 1;
                $sql_hdusers .= "
                    AND (". $DB->sql_like('hu.name', ':huname' . $i, false) . "
                    OR ". $DB->sql_like('hu.email', ':huemail' . $i, false) . ")";
                $sqlparams['huname' . $i] = $v;
                $sqlparams['huemail' . $i] = $v;
            }
            $search_count += $DB->count_records_sql('SELECT COUNT(*)' . $sql_hdusers, $sqlparams);
        }
        echo $OUTPUT->heading("$search_count / $full_count ".get_string('users'));
    } else {
        echo $OUTPUT->heading("$full_count ".get_string('users'));
        $search_count = $full_count;
    }

    $sql = '';
    if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_INTERNAL) {
        $sql .= "
            SELECT " . $DB->sql_concat("'u-'", "u.id") . " AS id,
                u.id AS userid, hu2.id AS hd_userid, u.email, u.firstname, u.lastname,
                COALESCE(u.phone1, u.phone2) AS phone, '' AS type
            $sql_users
        ";
    }
    if ($userset == HELPDESK_USERSET_ALL) {
        $sql .= "UNION";
    }
    if ($userset == HELPDESK_USERSET_ALL or $userset == HELPDESK_USERSET_EXTERNAL) {
        $sql .= "
            SELECT " . $DB->sql_concat("'hu-'", "hu.id") . " AS id,
                NULL AS userid, hu.id AS hd_userid, hu.email, hu.name AS firstname, '' AS lastname,
                hu.phone AS phone, hu.type AS type
            $sql_hdusers
        ";
    }
    $sql .= "
        ORDER BY firstname
        LIMIT $perpage
        OFFSET " . ($perpage * $page);

    $users = $DB->get_records_sql($sql, $sqlparams);
}

$thisurl->param('search', $search);
$thisurl->param('perpage', $perpage);
$thisurl = $thisurl->out() . '&';

$pagingbar = new paging_bar($search_count, $page, $perpage, $thisurl);
echo $OUTPUT->render($pagingbar);

flush();

foreach($users as $user) {
    if (!isset($user->hd_userid)) {
        $user->hd_userid = helpdesk_ensure_hd_user($user->userid);
    }
    $returnurl->param($paramname, $user->hd_userid);

    $changelink = fullname_nowarnings($user) . ' <small>(<a href="' . $returnurl->out() . '">' .
            $selecttext . '</a>)</small>';
    $table->data[] = array(
        $changelink,
        $user->email,
        $user->phone,
        isset($user->userid) ? get_string('internal_user', 'block_helpdesk') : $user->type,
    );
}
echo html_writer::table($table);
$pagingbar = new paging_bar($search_count, $page, $perpage, $thisurl);
echo $OUTPUT->render($pagingbar);

echo $OUTPUT->footer();
