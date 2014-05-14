<?php

// This file is part of Help Me Now block - http://moodle.org/
//
// Help Me Now block is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Help Me Now block is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Help Me Now block.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Post installation and migration code.
 *
 * @package    block_helpdesk
 * @copyright  2014 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_block_helpdesk_install() {
    global $DB, $USER;

    if (!$DB->count_records('role', array('shortname' => 'helpdeskmanager'))) {

        // Reminder: do not use core/lib function in upgrade script!
        $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_SYSTEM));

        // Hardcode the capability as they must match the value at this upgrade time.
        $HELPDESK_CAP_ASK = 'block/helpdesk:ask';
        $HELPDESK_CAP_ANSWER = 'block/helpdesk:answer';

        // Add Help Desk block manager system role.
        $role = new stdClass();
        $role->name        = 'Help Desk Manager';
        $role->shortname   = 'helpdeskmanager';
        $role->description = 'can answer questions - can do anything on helpdesk.';
        // Find free sortorder number.
        $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
        if (empty($role->sortorder)) {
            $role->sortorder = 1;
        }
        $roleid = $DB->insert_record('role', $role);
        // Set the role as system role.
        $rcl = new stdClass();
        $rcl->roleid = $roleid;
        $rcl->contextlevel = CONTEXT_SYSTEM;
        $DB->insert_record('role_context_levels', $rcl, false, true);
        // Assign correct permission to Help Me Now block manager role.
        $cap = new stdClass();
        $cap->contextid    = $contextid;
        $cap->roleid       = $roleid;
        $cap->capability   = $HELPDESK_CAP_ANSWER;
        $cap->permission   = 1;
        $cap->timemodified = time();
        $cap->modifierid   = empty($USER->id) ? 0 : $USER->id;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = $HELPDESK_CAP_ASK;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:view';
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:addinstance';
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:myaddinstance';
        $DB->insert_record('role_capabilities', $cap);

        // Add Help Desk block system role.
        $role = new stdClass();
        $role->name        = 'Help Desk user';
        $role->shortname   = 'helpdeskuser';
        $role->description = 'can ask question on help desk.';
        $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
        $roleid = $DB->insert_record('role', $role);
        $rcl = new stdClass();
        $rcl->roleid = $roleid;
        $rcl->contextlevel = CONTEXT_SYSTEM;
        $DB->insert_record('role_context_levels', $rcl, false, true);
        $cap->roleid       = $roleid;
        $cap->capability   = $HELPDESK_CAP_ASK;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:view';
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:addinstance';
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = 'block/helpdesk:myaddinstance';
        $DB->insert_record('role_capabilities', $cap);
    }
}