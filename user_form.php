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
 * Form for new/editing hd users
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once("$CFG->libdir/formslib.php");

class helpdesk_user_form extends moodleform {
    protected $new_user;

    function __construct($new_user = false, $action=null) {
        $this->new_user = $new_user;
        parent::__construct($action);
    }

    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('header', 'title', get_string($this->new_user ? 'new_user' : 'editexternal', 'block_helpdesk'));
        $mform->addElement('text', 'name', get_string('fullname'), 'size="40"');
        $mform->addRule('name', null, 'required');
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('text', 'email', get_string('email', 'block_helpdesk'), 'size="40"');
        // PARAM_EMAIL is the correct value but for backward compatibility let's put PARAM_TEXT.
        $mform->setType('email', PARAM_TEXT);
        $mform->addElement('text', 'phone', get_string('phone'), 'size="20"');
        $mform->setType('phone', PARAM_TEXT);

        $type_options = array_map(function ($option) {
                return trim($option);
            }, explode(',', $CFG->block_helpdesk_user_types));
        $type_options = array_combine($type_options, $type_options);
        $mform->addElement('select', 'type', get_string('usertype', 'block_helpdesk'), $type_options);
        $mform->setType('type', PARAM_TEXT);

        $mform->addElement('submit', 'submitbutton', get_string('submit'));


        $mform->addElement('hidden', 'function');
        $mform->setType('function', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_URL);
        $mform->addElement('hidden', 'paramname');
        $mform->setType('paramname', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'ticketid');
        $mform->setType('ticketid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
    }
}
