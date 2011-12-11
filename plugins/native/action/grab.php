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
 * @copyright   2010-2011 VLACS
 * @author      Joanthan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// At this point, we've very deep inside moodle, we're 7 sub directories away 
// from config, plus the file we're on.
$hd_depth = 4;
$www_depth = 6;
$path = __FILE__;
for($i = 1; $i <= $www_depth; $i++) {
    $path = dirname($path);
    if($i == $hd_depth) { $hdpath = $path; }
}

require_once("{$path}/config.php");
require_once("{$hdpath}/lib.php");

global $CFG, $USER;

require_login(0, false);

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true); // require answerer capability.

$hd = helpdesk::get_helpdesk();

/**
 * Hello! I'm an action script! I do cool things to tickets.
 * This particular script takes a new unassigned question and gives it to an 
 * answer, assigns them to it, and makes them the first responder instantly.
 * 
 * I suspect a queue might be more useful, but this works for now. --jdoane
 *
 * Keep mind this script is in NATIVE LAND, we don't need to worry about 
 * helpdesk and helpdesk_ticket abstraction. We can call helpdesk_native methods 
 * directly. That is the point of this script set.
 */

$id = required_param('id', PARAM_INT);
$ticket = $hd->get_ticket($id);

if(empty($ticket)) {
    error(get_string('noticket', 'block_helpdesk'));
}

$fc = $ticket->get_firstcontact();

if(!empty($fc)) {
    error(get_string('notavailabletograb', 'block_helpdesk'));
}

$ticket->set_firstcontact($USER->id);
if(!$ticket->store()) {
    error(get_string('unabletostoreticket', 'block_helpdesk'));
}

if(!$ticket->add_assignment($USER->id)) {
    error(get_string('unabletoaddassignment', 'block_helpdesk'));
}

$url = new moodle_url('/blocks/helpdesk/view.php');
$url->param('id', $ticket->get_idstring());

redirect($url->out(), get_string('questiongrabbed', 'block_helpdesk') . ': ' . $ticket->get_summary());
?>
