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
 * This is the answerer assignmennt script. Handles all direct assignment 
 * operations.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We are moodle, so we shall become moodle.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// We are also Helpdesk, so we shall also become a helpdesk.
require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

require_login(0, false);

// Grab optional params.
$tid    = required_param('tid', PARAM_INT);
$uid    = optional_param('uid', null, PARAM_INT);
$remove = optional_param('remove', null, PARAM_BOOL);
$page   = optional_param('page', null, PARAM_INT);
$count  = optional_param('count', null, PARAM_INT);
$count  = ($count == null ? 10 : $count);
$page   = ($page == null ? 0 : $page);

$context = context_system::instance();

$qurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$viewurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
$viewurl->param('id', $tid);

$nav = array (
    array (
        'name' => get_string('helpdesk', 'block_helpdesk'),
        'link' => $qurl->out()
    ),
    array (
        'name' => get_string('ticketview', 'block_helpdesk'),
        'link' => $viewurl->out()
    ),
    array (
        'name' => get_string('assignments', 'block_helpdesk')
    )
);

$title = get_string('helpdeskassignuser', 'block_helpdesk');
helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);

$hd = helpdesk::get_helpdesk();
$ticket = $hd->get_ticket($tid);
$returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
$returnurl->param('id', $tid);
$returnurl = $returnurl->out();

// What if we're removing an assignment? We want to handle that *first*.
if (!empty($remove)) {

    // We have data we need, we can continue to remove an assignment.
    if(!$ticket->remove_assignment($uid)) {
        print_error('cannotremoveassignment', 'block_helpdesk');
    }
    $str_unassigned = get_string('hasbeenunassigned', 'block_helpdesk');
    $str_username = fullname_nowarnings(helpdesk_get_user($uid));
    $url = new moodle_url("$CFG->wwwroot/user/view.php");
    $url->param('id', $uid);
    $url = $url->out();

    $text = "<a href=\"$url\">$str_username</a> $str_unassigned";

    redirect($returnurl, $text);
}

$assigned = $ticket->get_assigned();

if (!empty($uid)) {
    // If there is a user id. It means its time to add the user.
    // REMEMBER! One user can be assigned to one ticket, once.
    if (!empty($assigned)) {
        foreach($assigned as $element) {
            if ($uid != $element->userid) {
                continue;
            }
            $userurl = new moodle_url("$CFG->wwwroot/user/view.php");
            $userurl->param('id', $uid);
            $userurl = $userurl->out();
            $text = "<a href=\"$userurl\">" . fullname_nowarnings(helpdesk_get_user($element->userid)) .
                    "</a> " . get_string('isalreadyassigned', 'block_helpdesk');
            redirect($returnurl, $text);
            // Here a footer gets printed and the script stops.
            // We no longer need to worry about already assigned further in.
        }
    }
    if(!helpdesk_is_capable(HELPDESK_CAP_ANSWER, false, $uid)) {
        print_error('usernotananswerer', 'block_helpdesk');
    }
    // We want to add the assignment here.
    if(!$ticket->add_assignment($uid)) {
        print_error('cannotaddassignment', 'block_helpdesk');
    }
    redirect($returnurl, get_string('assignmentadded', 'block_helpdesk'));
}

// No user selected, so its time to find us one.
//helpdesk_print_header($nav, $title);
helpdesk::page_init($title, $nav);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('helpdesk', 'block_helpdesk'));

// We are starting from scratch here!
$offset = $page * $count;
$assignables = get_users_by_capability($context, HELPDESK_CAP_ANSWER, 'u.*', 'u.lastname ASC', $offset, $count);
// Add admin users to assignable users.
$allassignables = $assignables + get_admins();
$table = new html_table();
$table->head = array (
    get_string('name'),
    get_string('email'),
    ''
);
$table->data = array();
foreach($allassignables as $user) {
    $userurl = new moodle_url("$CFG->wwwroot/user/view.php");
    $userurl->param('id', $user->id);
    $emailurl = new moodle_url("mailto:$user->email");
    $email = $emailurl->out();
    $url = $userurl->out();
    $fullname = fullname_nowarnings($user);
    $assignurl = new moodle_url(qualified_me());
    $assignurl->param('uid', $user->id);
    $assign = $assignurl->out();
    $assignstr = get_string('assignthisuser', 'block_helpdesk');
    $table->data[] = array(
        "<a href=\"$url\">$fullname</a>",
        "<a href=\"$email\">$user->email</a>",
        "<a href=\"$assign\">$assignstr</a>",
        );
}
echo html_writer::table($table);

// This makes the paging bar.
$countfield = 'u.id';
$total = get_users_by_capability($context, HELPDESK_CAP_ANSWER, $countfield);
$total = count($total);
$url = new moodle_url(qualified_me());

$pagingbar = new paging_bar($total, $page, $count, $url, 'page');
echo $OUTPUT->render($pagingbar);

echo $OUTPUT->footer();
