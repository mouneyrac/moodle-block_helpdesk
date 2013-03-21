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
 * This script handles the updating of tickets by managing the UI and entry
 * level functions for the task.
 *
 * @package     block_helpdesk
 * @copyright   2010-2011 VLACS
 * @author      Joanthan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

// At this point, we've very deep inside moodle/helpdesk, we're a good
// number of sub-directories away from config and libs, plus the file we're on.
$hd_depth = 4;
$www_depth = 6;
$path = __FILE__;
for($i = 1; $i <= $www_depth; $i++) {
    $path = dirname($path);
    if($i == $hd_depth) { $hdpath = $path; }
}
require_once("{$path}/config.php");
require_once("{$hdpath}/lib.php");

// Sorry, you need to be able to login to get here.
require_login(0, false);
