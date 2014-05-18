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
 * This is the view script. It handles the UI and entry level function calls for
 * displaying a respective ticket. If no parameters are passed through post or
 * get, it will display a ticket listing for whatever user is logged on.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We are moodle, so we shall become moodle.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// We are also Helpdesk, so we shall also become a helpdesk.
require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

$id = required_param('id', PARAM_INT);
$token = optional_param('token', '', PARAM_ALPHANUM);

$url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
if (strlen($token)) {
    helpdesk_authenticate_token($id, $token);
    $readonly = empty($CFG->block_helpdesk_external_updates);
    $nav = array();
} else {
    require_login(0, false);
    $readonly = false;
    $nav = array(
        array (
            'name' => get_string('helpdesk', 'block_helpdesk'),
            'link' => $url->out()
        ),
        array(
            'name' => get_string('ticketviewer', 'block_helpdesk')
        )
    );
}
$title = get_string('helpdesk', 'block_helpdesk');
helpdesk::page_init($title, $nav);
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('ticketviewer', 'block_helpdesk'));

// Let's construct our helpdesk.
$hd = helpdesk::get_helpdesk();

// Display specific ticket.
$ticket = $hd->get_ticket($id);
if (!$ticket) {
    print_error('ticketiddoesnotexist', 'block_helpdesk');
}
$hd->display_ticket($ticket, $readonly);

helpdesk_print_footer();
