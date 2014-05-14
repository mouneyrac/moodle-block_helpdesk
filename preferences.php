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
 * This is a preferences script. This allows the user to change settings that
 * may alter how the helpdesk is viewed.
 *
 * @package     block_helpdesk
 * @copyright   2010
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We are moodle, so we shall become moodle.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// We are also Helpdesk, so we shall also become a helpdesk.
require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once("$CFG->dirroot/blocks/helpdesk/pref_form.php");

require_login(0, false);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/blocks/helpdesk/preferences.php');

$baseurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$nav = array (
    array ('name' => get_string('helpdesk', 'block_helpdesk'), 'link' => $baseurl->out()),
    array ('name' => get_string('preferences')),
);
$title = get_string('pref', 'block_helpdesk');


require_login();

if(!helpdesk_is_capable()) {
    print_error('nocapabilities', 'block_helpdesk');
}

// By default, these are disabled (false).
$preferences = new stdClass();
$preferences->showsystemupdates = (bool) helpdesk_get_session_var('showsystemupdates');
$preferences->showdetailedupdates = (bool) helpdesk_get_session_var('showdetailedupdates');

$form = new helpdesk_pref_form(qualified_me(), null, 'post');

// If not submitted, show form with current values.
if (!$form->is_submitted()) {
//    helpdesk_print_header($nav);
    helpdesk::page_init($title, $nav);
    echo $OUTPUT->header();
    $form->set_data($preferences);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// We have a submitted form, lets assume everything changed and update
// everything.
$data = $form->get_data();
foreach($data as $key => $value) {
    helpdesk_set_session_var($key, $value);
}

redirect($CFG->wwwroot, get_string('preferencesupdated', 'block_helpdesk'));

