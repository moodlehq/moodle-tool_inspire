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
 * Train system models with new data available.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\task;

/**
 * Train system models with new data available.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class train_models extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('trainmodels', 'tool_inspire');
    }

    public function execute() {
        global $DB;

        $models = $DB->get_records_select('tool_inspire_models', 'enabled = 1 AND timesplitting IS NOT NULL');
        if (!$models) {
            mtrace(get_string('errornoenabledmodels', 'tool_inspire'));
            return;
        }
        foreach ($models as $modelobj) {
            $model = new \tool_inspire\model($modelobj);

            $result = $model->train();
            if ($result) {
                mtrace('Results:');
                mtrace(' - Status code: ' . $result->status);
                if (!empty($result->errors)) {
                    foreach ($result->errors as $error) {
                        mtrace('   - ' . $error);
                    }
                }
            }
        }
    }
}
