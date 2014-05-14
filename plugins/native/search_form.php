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
 * New ticket form which extends a standard moodleform.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->libdir/formslib.php");

class search_form extends moodleform {
    function definition() {
        global $DB, $OUTPUT;

        $context = context_system::instance();

        // Return all that have rows, but not on the join itself.
        // We want to populate the answerers list with only users who have 
        $answerers = $DB->get_records_sql("
            SELECT DISTINCT (u.*)
            FROM {user} AS u
            LEFT JOIN {block_helpdesk_ticket_assign} AS hta ON u.id = hta.userid
            WHERE hta.ticketid IS NOT NULL
            ORDER BY u.lastname, u.firstname ASC
        ");

        $statuses = get_ticket_statuses();
        $statuslist = array();
        $statusdefault = array();
        foreach($statuses as $s) {
            $statuslist[$s->id] = get_status_string($s);
            $statusdefault[] = $s->id;
        }

        $answererlist = array(
            -1 => get_string('anyanswerer', 'block_helpdesk'),
            0 => get_string('noanswerers', 'block_helpdesk')
        );
        foreach($answerers as $a) {
            $answererlist[$a->id] = fullname_nowarnings($a);
        }

        $mform =& $this->_form;

        $help = $OUTPUT->help_icon('search', 'block_helpdesk');
        $searchphrase = get_string('searchphrase', 'block_helpdesk');
        $statusstr = get_string('status', 'block_helpdesk');
        $answererstr = get_string('answerer', 'block_helpdesk');

        // Elements
        $mform->addElement('header', 'frm', get_string('search'));
        $mform->addElement('text', 'searchstring', $searchphrase . $help);
        $mform->setType('searchstring', PARAM_TEXT);

        $adv = array();
        $statuselement =& $mform->createElement('select', 'status', $statusstr, $statuslist);
        $statuselement->setMultiple(true);
        $mform->addElement($statuselement);
        // TODO: Configure this later. --jdoane
        //$adv =& $mform->createElement('select', 'orderby', $orderbystr, $orderbylist);
        $mform->addElement('select', 'answerer', $answererstr, $answererlist);

        // Rules
        $mform->addRule('status', null, 'required', 'server');
        $mform->setAdvanced('answerer', true);
        $mform->setAdvanced('status', true);
        $mform->setDefault('answerer', -1);
        $mform->setDefault('status', $statusdefault);
        $mform->addElement('submit', 'submitbutton', get_string('search'));
        $mform->addElement('hidden', 'submitter', '');
        $mform->setType('submitter', PARAM_INT);
    }

    function validation($date, $files) {
        // Add something at some point.
    }

    function set_multiselect_default($array) {
        $mform =& $this->_form;
        $mform->setDefault('status', $array);
    }
}
