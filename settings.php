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
 * @package tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage('inspiremodels', get_string('inspiremodels', 'tool_inspire'), "$CFG->wwwroot/$CFG->admin/tool/inspire/index.php"));

if ($hassiteconfig) {
    $settings = new admin_settingpage('inspiremanagement', new lang_string('pluginname', 'tool_inspire'));
    $ADMIN->add('tools', $settings);

    // Select the site prediction's processor.
    $predictionprocessors = \tool_inspire\manager::get_all_prediction_processors();
    $predictors = array();
    foreach ($predictionprocessors as $fullclassname => $predictor) {
        $pluginname = substr($fullclassname, 1, strpos($fullclassname, '\\', 1) - 1);
        $predictors[$fullclassname] = new lang_string('pluginname', $pluginname);
    }
    $settings->add(new admin_setting_configselect('tool_inspire/predictionsprocessor',
        new lang_string('predictionsprocessor', 'tool_inspire'), '', '\predict_php\processor', $predictors));

    // Enable/disable time splitting methods.
    $alltimesplittings = \tool_inspire\manager::get_all_time_splittings();

    $timesplittingoptions = array();
    $timesplittingdefaults = array();
    foreach ($alltimesplittings as $key => $timesplitting) {
        $timesplittingoptions[$key] = $timesplitting->get_name();
        $timesplittingdefaults[] = $key;
    }
    $settings->add(new admin_setting_configmultiselect('tool_inspire/timesplittings',
        new lang_string('enabledtimesplittings', 'tool_inspire'), '', $timesplittingdefaults, $timesplittingoptions));

    // Predictions processor output dir.
    $defaultmodeloutputdir = rtrim($CFG->dataroot, '/') . DIRECTORY_SEPARATOR . 'models';
    $settings->add(new admin_setting_configtext('tool_inspire/modeloutputdir', new lang_string('modeloutputdir', 'tool_inspire'),
        new lang_string('modeloutputdirinfo', 'tool_inspire'), $defaultmodeloutputdir, PARAM_PATH));
    $studentdefaultroles = [];
    $teacherdefaultroles = [];

    // Student and teacher roles.
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

    $settings->add(new admin_setting_configmultiselect('tool_inspire/teacherroles', new lang_string('teacherroles', 'tool_inspire'),
       '', $teacherdefaultroles, $rolechoices));

    $settings->add(new admin_setting_configmultiselect('tool_inspire/studentroles', new lang_string('studentroles', 'tool_inspire'),
       '', $studentdefaultroles, $rolechoices));

}
