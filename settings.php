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
 * This script handles global settings for this Help Desk block.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Joanthan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

$settings->add(new admin_setting_heading('block_helpdesk_general',
    get_string('generalsettings', 'block_helpdesk'),
    get_string('generalsettingsdesc', 'block_helpdesk')));

$settings->add(new admin_setting_configtext('block_helpdesk_block_name',
    get_string('blocknameconfig', 'block_helpdesk'),
    get_string('blocknameconfigdesc', 'block_helpdesk'),
    '', PARAM_TEXT));

$settings->add(new admin_setting_configtext('block_helpdesk_submit_text',
    get_string('submittextconfig', 'block_helpdesk'),
    get_string('submittextconfigdesc', 'block_helpdesk'),
    '', PARAM_TEXT));

$settings->add(new admin_setting_configcheckbox('block_helpdesk_allow_external_users',
    get_string('allowexternal', 'block_helpdesk'),
    get_string('allowexternaldesc', 'block_helpdesk'),
    '0', '1', '0'));

$settings->add(new admin_setting_configtext('block_helpdesk_user_types',
    get_string('usertypesconfig', 'block_helpdesk'),
    get_string('usertypesconfigdesc', 'block_helpdesk'),
    'student,teacher,guardian', PARAM_TEXT));

$settings->add(new admin_setting_configcheckbox('block_helpdesk_external_user_tokens',
    get_string('allowexternaltokens', 'block_helpdesk'),
    get_string('allowexternaltokensdesc', 'block_helpdesk'),
    '0', '1', '0'));

$settings->add(new admin_setting_configtext('block_helpdesk_token_exp',
    get_string('tokenexp', 'block_helpdesk'),
    get_string('tokenexpdesc', 'block_helpdesk'),
    HELPDESK_DEFAULT_TOKEN_EXP, PARAM_INT));

$settings->add(new admin_setting_configcheckbox('block_helpdesk_external_updates',
    get_string('allowexternalupdates', 'block_helpdesk'),
    get_string('allowexternalupdatesdesc', 'block_helpdesk'),
    '0', '1', '0'));

$hd = helpdesk::get_helpdesk();
if (method_exists($hd, 'plugin_settings')) {
    $hd->plugin_settings($settings);
}
