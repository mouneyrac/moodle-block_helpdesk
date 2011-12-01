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
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/weblib.php');

// We are also Helpdesk, so we shall also become a helpdesk.
require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

require_login(0, false);

$id         = required_param('id', PARAM_INT);

$url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/search.php");
$nav = array(array (
    'name' => get_string('helpdesk', 'block_helpdesk'),
    'link' => $url->out()
));
$heading = get_string('helpdesk', 'block_helpdesk');
if (isset($id)) {
    $nav[] = array('name' => get_string('ticketviewer', 'block_helpdesk'));
    $heading = get_string('ticketviewer', 'block_helpdesk');
}

$title = get_string('helpdeskticketviewer', 'block_helpdesk');

helpdesk_print_header(build_navigation($nav), $title);
print_heading($heading);

// Let's construct our helpdesk.
$hd = helpdesk::get_helpdesk();

// Display specific ticket.
$ticket = $hd->get_ticket($id);
if (!$ticket) {
    error(get_string('ticketiddoesnotexist','block_helpdesk'));
}
$hd->display_ticket($ticket);

print_footer();
?>
