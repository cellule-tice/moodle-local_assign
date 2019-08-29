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
 * Add page to admin menu.
 *
 * @package local_workshop
 * @copyright 2018 - Cellule TICE - Université de Namur
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // Needs this condition or there is error on login page.
    $settings = new admin_settingpage('local_assignaddons',
    get_string('pluginname', 'local_assignaddons'));
    $ADMIN->add('localplugins', $settings);

    // Courselist for which link must be displayed.

    $settings->add(new admin_setting_configtext('local_assignaddons/courselistwithfillinsubmissionslink',
            get_string('display_fillinmissingsubmission_link_for_courses', 'local_assignaddons'),
        get_string('display_fillinmissingsubmission_link_for_courses', 'local_assignaddons'), 'SSPSB315,SELVB101'));

}