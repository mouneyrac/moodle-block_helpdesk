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
 * view/edit/create hdusers
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Joanthan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_once("$CFG->dirroot/blocks/helpdesk/lib.php");
require_once(dirname(__FILE__) . '/user_form.php');

require_login(0, false);

$returnurl      = required_param('returnurl', PARAM_RAW);
$paramname      = optional_param('paramname', null, PARAM_TEXT);
$id             = optional_param('id', null, PARAM_INT);

$baseurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
$searchurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/hduser.php");
$nav = array (
    array (
        'name' => get_string('helpdesk', 'block_helpdesk'),
        'link' => $searchurl->out()
    ),
    array(
        'name' => get_string('manageexternal', 'block_helpdesk'),
        'link' => $returnurl,
    ),
    array (
        'name' => get_string('edit_user', 'block_helpdesk'),
    ),
);
$title = get_string('helpdeskuser', 'block_helpdesk');

helpdesk::page_init($title, $nav);

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);

if (!$CFG->block_helpdesk_allow_external_users) {
    print_error('externalusersdisabled', 'block_helpdesk');
}

$user_form = new helpdesk_user_form();

if ($user_form->is_cancelled()) {
    # todo: redirect
    # can we get the hidden fields from a cancelled form?
} else if ($user = $user_form->get_data()) {
    if (!empty($user->id)) {
        if (!$DB->update_record('block_helpdesk_hd_user', $user)) {
            print_error('externaluserupdatefailed', 'block_helpdesk');
        }
        $rval = $user->id;
    } else {
        if (!$rval = $DB->insert_record('block_helpdesk_hd_user', $user)) {
            print_error('externaluserupdatefailed', 'block_helpdesk');
        }
    }
    $url = new moodle_url($returnurl);
    if ($paramname) {
        $url->param($paramname, $rval);
    }
    redirect($url->out());
}

echo $OUTPUT->header();
if (!$toform = $DB->get_record('block_helpdesk_hd_user', array('id' => $id))) {
    print_error('nouser', 'block_helpdesk');
}
$toform->returnurl = $returnurl;
$toform->paramname = $paramname;
$user_form->set_data($toform);
$user_form->display();

echo $OUTPUT->footer();
