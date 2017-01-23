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
 * Tool inspire install function.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tool inspire install function.
 *
 * @return void
 */
function xmldb_tool_inspire_install() {
    global $DB, $USER;

    // TODO All of them for the moment, we will define a limited set once ready to release.
    $indicators = \tool_inspire\manager::get_all_indicators();

    $model = new stdClass();
    $model->codename = 'dropout';
    $model->target = '\tool_inspire\local\target\grade_pass';
    $model->indicators = json_encode(array_keys($indicators));

    // Standard 0.7 score to validate the model.
    $model->evaluationminscore = 0.7;

    // We don't require high prediction scores, if there is a risk we consider it valid enough to perform further action.
    $model->predictionminscore = 0.6;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->usermodified = $USER->id;

    $DB->insert_record('tool_inspire_models', $model);
}
