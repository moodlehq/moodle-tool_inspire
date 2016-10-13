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
 * Adds settings links to admin tree.
 *
 * @package tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('researchmanagement', new lang_string('pluginname', 'tool_research'));
    $ADMIN->add('tools', $settings);

    $studentdefaultroles = [];
    $teacherdefaultroles = [];

    $allroles = role_fix_names(get_all_roles());
    $rolechoices = [];
    foreach ($allroles as $role) {
        $rolechoices[$role->id] = $role->localname;

        if ($role->shortname == 'student') {
            $studentdefaultroles[] = $role->id;
        } else if ($role->shortname == 'teacher') {
            $teacherdefaultroles[] = $role->id;
        } else if ($role->shortname == 'editingteacher') {
            $teacherdefaultroles[] = $role->id;
        }
    }

    $settings->add(new admin_setting_configmultiselect('tool_research/teacherroles', new lang_string('teacherroles', 'tool_research'),
       '', $teacherdefaultroles, $rolechoices));

    $settings->add(new admin_setting_configmultiselect('tool_research/studentroles', new lang_string('studentroles', 'tool_research'),
       '', $studentdefaultroles, $rolechoices));

}
