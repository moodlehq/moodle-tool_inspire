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
 * Prediction view page.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Prediction view page.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prediction implements \renderable, \templatable {

    protected $model;
    protected $prediction;

    public function __construct(\tool_inspire\model $model, $prediction) {
        $this->model = $model;
        $this->prediction = $prediction;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {

        $data = new \stdClass();

        $data->predictiondisplayvalue = $this->model->get_target()->get_display_value($this->prediction->prediction);
        $data->predictionstyle = $this->model->get_target()->get_value_style($this->prediction->prediction);

        $calculations = json_decode($this->prediction->calculations, true);

        $info = array();
        foreach ($calculations as $indicatorkey => $value) {

            // Some indicator result in more than 1 feature, we need to see which feature are we dealing with.
            $separatorpos = strpos($indicatorkey, '/');
            if ($separatorpos !== false) {
                $subtype = substr($indicatorkey, ($separatorpos + 1));
                $indicatorkey = substr($indicatorkey, 0, $separatorpos);
            } else {
                $subtype = false;
            }

            if ($indicatorkey === 'range') {
                // Time range indicators don't belong to any indicator class, we don't show them.
                continue;
            } else if (!\tool_inspire\manager::is_valid($indicatorkey, '\tool_inspire\local\indicator\base')) {
                throw new \moodle_exception('errorpredictionformat', 'tool_inspire');
            }

            $indicator = \tool_inspire\manager::get_indicator($indicatorkey);

            // Hook for indicators with extra features that should not be displayed (like discrete indicators).
            if (!$indicator->should_be_displayed($value, $subtype)) {
                continue;
            }

            $obj = new \stdClass();
            $obj->name = $indicator::get_name($subtype);
            if ($value !== null) {
                $obj->displayvalue = $indicator->get_display_value($value, $subtype);
                $obj->style = $indicator->get_value_style($value, $subtype);
            } else {
                // Null case is special, it does not represent a central value but a "can't be calculated".
                $obj->displayvalue = '';
                $obj->style = '';
            }
            $info[] = $obj;
        }

        $data->calculations = $info;
        return $data;
    }
}
