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
 * Tag form for adding tags to a ticket. Extends moodleform.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once("$CFG->dirroot/blocks/helpdesk/plugins/native/helpdesk_native.php");

class tag_ticket_form extends moodleform {
    private $ticketid;

    function definition() {
        global $CFG;

        $mform =& $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];

        // Status Array

        $mform->addElement('header', 'frm', get_string('tickettag', 'block_helpdesk'));
        $mform->addElement('text', 'name', get_string('tagname', 'block_helpdesk'));
        $mform->addRule('name', null, 'required', 'server');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'value_editor', get_string('tagcontent', 'block_helpdesk'),
            null, $editoroptions);
        $mform->setType('value_editor', PARAM_RAW);
        $mform->addRule('value_editor', get_string('required'), 'required', null, 'client');

        $mform->addElement('submit', 'submitbutton', get_string('addtag', 'block_helpdesk'));
    }

    function validation($data, $files) {
        // We'll do custom validation if we ever need to.
        return array();
    }
}
