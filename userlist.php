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
require_once("$CFG->dirroot/user/filters/lib.php");

require_login(0, false);

$returnurl      = required_param('returnurl', PARAM_RAW);
$paramname      = required_param('paramname', PARAM_ALPHA);
$ticketid       = optional_param('tid', null, PARAM_INT);
$userid         = optional_param('userid', null, PARAM_INT);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 20, PARAM_INT);
$sort           = optional_param('sort', 'name', PARAM_ALPHA);
$dir            = optional_param('dir', 'ASC', PARAM_ALPHA);

if ($sort == "name") {
    $sort = "firstname";
}

$searchurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$thisurl = new moodle_url(me());
$thisurl->remove_params();
$thisurl->param('paramname', $paramname);
$thisurl->param('returnurl', $returnurl);

$nav = array (
    array (
        'name' => get_string('helpdesk', 'block_helpdesk'),
        'link' => $searchurl->out()
    )
);
if (is_numeric($ticketid)) {
    $ticketreturn = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
    $ticketreturn->param('id', $ticketid);
    $nav[] = array (
        'name' => get_string('ticketview', 'block_helpdesk'),
        'link' => $ticketreturn->out()
    );
}
$nav[] = array (
    'name' => get_string('updateticketoverview', 'block_helpdesk'),
    'link' => $returnurl
);
$nav[] = array (
    'name' => get_string('selectauser', 'block_helpdesk'),
);
$url = new moodle_url("{$CFG->wwwroot}/blocks/helpdesk/userlist.php");
$title = get_string('changeuser', 'block_helpdesk');
helpdesk::page_init($title, $nav, $url);
helpdesk::page_header();

$hd = helpdesk::get_helpdesk();

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);

$ufiltering = new user_filtering(null, $FULLME);

$columns = array ('fullname', 'email');
$table = new html_table();
$table->head = array();
$table->data = array();

foreach ($columns as $column) {
    if ($column == '') {
        $table->head[] = '';
        continue;
    }
    $table->head[$column] = get_string("$column");
}

$extrasql = $ufiltering->get_sql_filter();
list($esql, $eparams) = $extrasql;
$users = get_users(true, '', true, array(), "$sort $dir", '', '', $page, $perpage, '*', $esql, $eparams);
$usercount = get_users(false);
$usersearchcount = get_users(false, '', true, array(), "{$sort} ASC", '', '', $page, $perpage, '*', $esql, $eparams);

if ($extrasql !== '') {
    echo $OUTPUT->heading("$usersearchcount / $usercount ".get_string('users'));
    $usercount = $usersearchcount;
} else {
    echo $OUTPUT->heading("$usercount ".get_string('users'));
}

$thisurl->param('sort', $sort);
$thisurl->param('dir', $dir);
$thisurl->param('perpage', $perpage);
$thisurl = $thisurl->out() . '&';

echo $OUTPUT->paging_bar($usercount, $page, $perpage, $thisurl);

flush();

foreach($users as $user) {
    $url = new moodle_url($returnurl);
    $url->param($paramname, $user->id);

    $changelink = fullname($user) . ' <small>(<a href="' . $url->out() . '">' .
            get_string('selectuser', 'block_helpdesk') . '</a>)</small>';
    $table->data[] = array(
        $changelink,
        $user->email
    );
}
$ufiltering->display_add();
$ufiltering->display_active();
echo html_writer::table($table);
echo $OUTPUT->paging_bar($usercount, $page, $perpage, $thisurl);

echo $OUTPUT->footer();
