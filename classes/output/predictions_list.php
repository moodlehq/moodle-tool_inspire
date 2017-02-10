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

    protected $model;
    protected $context;

    public function __construct(\tool_inspire\model $model, \context $context) {
        $this->model = $model;
        $this->context = $context;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {

        $data = new \stdClass();

        // The model target is responsible of defining the template where the samples will be shown.
        $data->templatename = $this->model->get_target()->sample_template();

        $predictions = $this->model->get_predictions($this->context);

        $data->predictions = array();
        foreach ($predictions as $prediction) {
            $predictionrenderable = new \tool_inspire\output\prediction($prediction, $this->model);
            $data->predictions[] = $predictionrenderable->export_for_template($output);
        }

        return $data;
    }
}
