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
 * Update form. This handles updates to a ticket, not updating the ticket 
 * itself. Extends moodleform.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->libdir/formslib.php");

class update_ticket_form extends moodleform {
    function definition() {
        global $CFG, $DB;
        $editoroptions = $this->_customdata['editoroptions'];
        $ticket = $this->_customdata['ticket'];

        $mform =& $this->_form;

        // Status Array
        $status = array();

        // Get ticket so we know what status we can change this ticket to.
        $hd = helpdesk::get_helpdesk();

        $mform->addElement('header', 'frm', get_string('updateticket', 'block_helpdesk'));

        $mform->addElement('editor', 'notes_editor', get_string('notes', 'block_helpdesk'), null, $editoroptions);
        $mform->setType('notes_editor', PARAM_RAW);
        $mform->addRule('notes_editor', get_string('required'), 'required', null, 'client');

        // Add status
        if (!is_object($ticket)) {
            print_error('add_status() requires a ticket object when called.');
        }
        $status = $ticket->get_status();
        // Okay! New statuses so we have to to figure out status paths for
        // a given capability. (This sounds worse than it really is.)
        $cap = helpdesk_is_capable();
        if($cap == false) {
            print_error('Unable to get capability for statuses.');
        }
        $sql = "SELECT s.*
                FROM {block_helpdesk_status} s
                    JOIN {block_helpdesk_status_path} sp ON sp.tostatusid=s.id
                WHERE sp.fromstatusid = :statusid
                    AND sp.capabilityname = :capability";
        $sqlparams = array('statusid' => $status->id, 'capability' => $cap);
        $pstatuses = $DB->get_records_sql($sql, $sqlparams);

        if (empty($pstatuses)) {
            print_error('No paths from this status!');
        }
        $statuslist[HELPDESK_NATIVE_UPDATE_COMMENT] = get_string(HELPDESK_NATIVE_UPDATE_COMMENT,
            'block_helpdesk');
        foreach($pstatuses as $pstatus) {
            $statuslist[$pstatus->id] = $ticket->get_status_string($pstatus);
        }

        $mform->addElement('select', 'status', get_string('updatestatus', 'block_helpdesk'), $statuslist);

        if (helpdesk_is_capable(HELPDESK_CAP_ANSWER)) {
            $mform->addElement('checkbox', 'hidden', get_string('hideupdate', 'block_helpdesk'));
        }

        $mform->addElement('submit', 'submitbutton', get_string('updateticket', 'block_helpdesk'));
    }

    function validation($data, $files) {
        // If we need to do more here we will.
        return array();
    }
}
