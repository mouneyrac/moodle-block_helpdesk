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
 * This script extends a moodle block_base and is the entry point for all
 * helpdesk  ability.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// This version is posterior to the MOODLE_23_STABLE branch. This version has been backported from
// MOODLE_27_STABLE. Do not upgrade to MOODLE_23_STABLE, go directly to MOODLE_27_STABLE.
// This version has been only created for upgrading 1.9 sites, so a transition is required to 2.2.
$plugin->version = 2014041902;
$plugin->requires  = 2011120511.00;        // Requires this Moodle 2.6
$plugin->component = 'block_helpdesk';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '2.7 (Plugin Build: 2014041900)';
