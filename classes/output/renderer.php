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
 * Renderer.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use templatable;
use renderable;

/**
 * Renderer class.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param templatable $renderable
     * @return string HTML
     */
    protected function render_models_list(templatable $renderable) {
        $data = $renderable->export_for_template($this);
        return parent::render_from_template('tool_inspire/models_list', $data);
    }

    /**
     * Renders the list of predictions
     *
     * @param renderable $renderable
     * @return string HTML
     */
    protected function render_predictions_list(renderable $renderable) {
        $data = $renderable->export_for_template($this);
        return parent::render_from_template('tool_inspire/predictions_list', $data);
    }

    /**
     * Renders a prediction
     *
     * @param renderable $renderable
     * @return string HTML
     */
    protected function render_prediction(renderable $renderable) {
        $data = $renderable->export_for_template($this);
        return parent::render_from_template('tool_inspire/prediction_details', $data);
    }

    /**
     * Renders a table.
     *
     * @param \table_sql $table
     * @return string HTML
     */
    public function render_table(\table_sql $table) {
        $o = '';
        ob_start();
        $table->out(10, true);
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Web interface evaluate results.
     *
     * @param \stdClass[] $results
     * @param string[] $executionlog
     * @return string HTML
     */
    public function render_evaluate_results($results, $executionlog = array()) {
        global $OUTPUT;

        foreach ($results as $timesplittingid => $result) {

            $timesplitting = \tool_inspire\manager::get_time_splitting($timesplittingid);
            $langstrdata = (object)array('name' => $timesplitting->get_name(), 'id' => $timesplittingid);
            echo $OUTPUT->heading(get_string('executionresults', 'tool_inspire', $langstrdata), 3);

            if ($result->status == 0) {
                echo $OUTPUT->notification(get_string('goodmodel', 'tool_inspire', $result->status),
                    \core\output\notification::NOTIFY_SUCCESS);
            } else if ($result->status === \tool_inspire\model::GENERAL_ERROR ||
                    $result->status === \tool_inspire\model::NO_DATASET) {
                echo $OUTPUT->notification(get_string('generalerror', 'tool_inspire', $result->status),
                    \core\output\notification::NOTIFY_ERROR);
            }

            // Not an else if because we can have them both.
            if ($result->status & \tool_inspire\model::EVALUATE_LOW_SCORE) {
                echo $OUTPUT->notification(get_string('lowaccuracy', 'tool_inspire'),
                    \core\output\notification::NOTIFY_ERROR);
            }
            if ($result->status & \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA) {
                echo $OUTPUT->notification(get_string('notenoughdata', 'tool_inspire'),
                    \core\output\notification::NOTIFY_ERROR);
            }

            // Score.
            echo $OUTPUT->heading(get_string('score', 'tool_inspire') . ': ' . round(floatval($result->score), 4) * 100  . '%', 4);

            if (!empty($result->errors)) {
                foreach ($result->errors as $error) {
                    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_WARNING);
                }
            }
        }

        echo $OUTPUT->heading(get_string('extrainfo', 'tool_inspire'), 4);

        // Info logged during execution.
        if (!empty($executionlog)) {
            foreach ($executionlog as $log) {
                echo $OUTPUT->notification($log, \core\output\notification::NOTIFY_WARNING);
            }
        }

    }


    /**
     * Web interface execution results.
     *
     * @param array $trainresults
     * @param string[] $trainlogs
     * @param array $predictresults
     * @param string[] $predictlogs
     * @return string HTML
     */
    public function render_execute_results($trainresults, $trainlogs = array(), $predictresults, $predictlogs = array()) {
        // TODO
        var_dump($trainresults);
        var_dump($trainlogs);
        var_dump($predictresults);
        var_dump($predictlogs);
    }

}
