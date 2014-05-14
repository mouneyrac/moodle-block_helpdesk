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
define('MOODLE_INTERNAL', true);
require_once('init.php');

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true); // require answerer capability.
$id = required_param('id', PARAM_INT); // this is a ticket update id.

$update = $DB->get_record('block_helpdesk_ticket_update', array('id' => $id), 'id, hidden, ticketid');
$update->hidden = 0;
if(!$DB->update_record('block_helpdesk_ticket_update', $update)) {
    print_error('unabletoshowupdate', 'block_helpdesk');
}

$url = new moodle_url("{$CFG->wwwroot}/blocks/helpdesk/view.php");
$url->param('id', $update->ticketid);
redirect($url->out(), get_string('updatewillnowbeshown', 'block_helpdesk'));
