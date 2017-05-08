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
 * Inspire predictions list page.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Shows tool_inspire predictions list.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class predictions_list implements \renderable, \templatable {

    /**
     * @var \tool_inspire\model
     */
    protected $model;

    /**
     * @var \context
     */
    protected $context;

    /**
     * @var \tool_inspire\model
     */
    protected $othermodels;

    public function __construct(\tool_inspire\model $model, \context $context, $othermodels) {
        $this->model = $model;
        $this->context = $context;
        $this->othermodels = $othermodels;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        global $PAGE;

        $data = new \stdClass();

        $predictions = $this->model->get_predictions($this->context);

        $data->predictions = array();
        foreach ($predictions as $prediction) {
            $predictionrenderable = new \tool_inspire\output\prediction($prediction, $this->model);
            $data->predictions[] = $predictionrenderable->export_for_template($output);
        }

        if (empty($data->predictions)) {
            $notification = new \core\output\notification(get_string('nopredictionsyet', 'tool_inspire')); 
            $data->nopredictions = $notification->export_for_template($output);
        }

        if ($this->othermodels) {

            $options = array();
            foreach ($this->othermodels as $model) {
                $options[$model->get_id()] = $model->get_target()->get_name();
            }

            // New moodle_url instance returned by magic_get_url.
            $url = $PAGE->url;
            $url->remove_params('modelid');
            $modelselector = new \single_select($url, 'modelid', $options, '',
                array('' => get_string('selectotherinsights', 'tool_inspire')));
            $data->modelselector = $modelselector->export_for_template($output);
        }

        return $data;
    }
}
