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
 * Simple form for searching users
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once("$CFG->libdir/formslib.php");

class helpdesk_usersearch_form extends moodleform {
    protected $userset;

    function __construct($userset = HELPDESK_USERSET_ALL) {
        $this->userset = $userset;
        parent::__construct();
    }

    function definition() {
        global $CFG;

        $mform =& $this->_form;

        switch ($this->userset) {
        case HELPDESK_USERSET_ALL:
            $header = get_string('searchall', 'block_helpdesk');
            break;
        case HELPDESK_USERSET_INTERNAL:
            $header = get_string('searchinternal', 'block_helpdesk');
            break;
        case HELPDESK_USERSET_EXTERNAL:
            $header = get_string('searchexternal', 'block_helpdesk');
            break;
        }

        $mform->addElement('header', 'usersearch', $header);

        $mform->addElement('text', 'search', '', 'size="40"');
        $mform->setType('search', PARAM_TEXT);
        $mform->addElement('submit', 'submitbutton', get_string('search'));

        $mform->addElement('hidden', 'function');
        $mform->setType('function', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_URL);
        $mform->addElement('hidden', 'paramname');
        $mform->setType('paramname', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'tid');
        $mform->setType('tid', PARAM_INT);
    }
}
