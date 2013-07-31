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
 * This script is for creating new tickets and handling the UI and entry level
 * functions of this task.
 *
 * @package     block_helpdesk
 * @copyright   2010
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We are moodle, so we should get necessary stuff.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// We are the helpdesk, so we need the core library.
require_once($CFG->dirroot . '/blocks/helpdesk/lib.php');

require_login(0, false);

// User should be logged in, no guests.

$baseurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$url = new moodle_url("/blocks/helpdesk/new.php");

$toform = array();
// We may have some special tags included in GET.
$tags = optional_param('tags', null, PARAM_TAGLIST);
$tagslist = array();
if (!empty($tags)) {
    $tagssplit = explode(',', $tags);
    foreach($tagssplit as $tag) {
        if (!($rval = optional_param($tag, null, PARAM_TEXT))) {
            notify(get_string('missingnewtickettag', 'block_helpdesk') . ": $tag");
        }
        $taglist[$tag] = $rval;
    }
    $url->param('tags', $tags);
    $toform['tags'] = $taglist;
}

$hd_userid = optional_param('hd_userid', 0, PARAM_INT);
if ($hd_userid) {
    helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);
    $url->param('hd_userid', $hd_userid);
    $toform['hd_userid'] = $hd_userid;
}

// Require a minimum of asker capability on the current user.
helpdesk_is_capable(HELPDESK_CAP_ASK, true);

$nav = array (
    array ('name' => get_string('helpdesk', 'block_helpdesk'), 'link' => $baseurl->out()),
    array ('name' => get_string('newticket', 'block_helpdesk')),
);
$title = get_string('helpdesknewticket', 'block_helpdesk');

// Meat and potatoes of the new ticket.
// Get plugin helpdesk.
$hd = helpdesk::get_helpdesk();

// Get new ticket form to get data or the form itself.
$form = $hd->new_ticket_form($toform);

// If the form is submitted (or not) we gotta do stuff.
if (!$form->is_submitted() or !($data = $form->get_data())) {
    helpdesk_print_header(build_navigation($nav), $title);
    print_heading(get_string('helpdesk', 'block_helpdesk'));
    if ($hd_userid) {
        $user = helpdesk_get_hd_user($hd_userid);
        print_heading(get_string('submittingas', 'block_helpdesk') . fullname($user));
    }
    $form->display();
    print_footer();
    exit;
}

// At this point we know that we have a ticket to add.
$ticket = $hd->new_ticket();
if (!$ticket->parse($data)) {
    error(get_string("cannotparsedata", 'block_helpdesk'));
}
if ($hd_userid) { $ticket->set_firstcontact($USER->id); }
if (!$ticket->store()) {
    error(get_string('unabletostoreticket', 'block_helpdesk'));
}
$id = $ticket->get_idstring();

if ($hd_userid) {
    $ticket->add_assignment($USER->id);
    if ($CFG->block_helpdesk_assigned_auto_watch) {
        $user = helpdesk_get_user($USER->id);
        if (!$ticket->add_watcher($user->hd_userid)) {
            error(get_string('cannotaddwatcher', 'block_helpdesk'));
        }
    }
}

if (!empty($data->tags)) {
    $taglist = array();
    $tags = explode(',', $data->tags);
    foreach($tags as $tag) {
        if (!($rval = $data->$tag)) {
            notify(get_string('missingnewtickettag', 'block_helpdesk') . ": $tag");
        } else {
            $taglist[$tag] = $rval;
        }
    }

    foreach($taglist as $key => $value) {
        $tagobject = new stdClass;
        $tagobject->ticketid = $id;
        $tagobject->name = $key;
        $tagobject->value = $value;
        if (!$ticket->add_tag($tagobject)) {
            notify(get_string('cannotaddtag', 'block_helpdesk') . ": $key");
        }
    }
}

$url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
$url->param('id', $id);
$url = $url->out();
redirect($url, get_string('newticketmsg', 'block_helpdesk'));
