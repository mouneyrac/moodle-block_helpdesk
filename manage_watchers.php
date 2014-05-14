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
 * This is the watcher add/remove script
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

require_login(0, false);

// Grab optional params.
$hd_userid  = required_param('hd_userid', PARAM_INT);
$tid   = required_param('tid', PARAM_INT);
$remove     = optional_param('remove', null, PARAM_BOOL);

helpdesk_is_capable(HELPDESK_CAP_ANSWER, true);

$hd = helpdesk::get_helpdesk();
$ticket = $hd->get_ticket($tid);
$returnurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/view.php");
$returnurl->param('id', $tid);
$returnurl = $returnurl->out();

// What if we're removing a watcher? We want to handle that *first*.
if ($remove) {

    // We have data we need, we can continue to remove a watcher.
    if(!$ticket->remove_watcher($hd_userid)) {
        print_error('cannotremovewatcher', 'block_helpdesk');
    }
    $user = helpdesk_get_hd_user($hd_userid);
    $text = helpdesk_user_link($user) .  get_string('nolongerwatching', 'block_helpdesk');

    redirect($returnurl);   //, $text);
}

// not removing? must be adding!
if (!$ticket->add_watcher($hd_userid)) {
    print_error('cannotaddwatcher', 'block_helpdesk');
}
$user = helpdesk_get_hd_user($hd_userid);
$text = helpdesk_user_link($user) .  get_string('watching', 'block_helpdesk');

redirect($returnurl);   //, $text);
