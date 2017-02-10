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

    public function __construct(\tool_inspire\prediction $prediction, \tool_inspire\model $model) {
        $this->prediction = $prediction;
        $this->model = $model;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {

        $data = new \stdClass();

        $data->sampledata = $this->prediction->get_sample_data();

        $predictedvalue = $this->prediction->get_prediction_data()->prediction;
        $predictionid = $this->prediction->get_prediction_data()->id;
        $data->predictiondisplayvalue = $this->model->get_target()->get_display_value($predictedvalue);
        $data->predictionstyle = $this->model->get_target()->get_value_style($predictedvalue);
        $predictionurl = new \moodle_url('/admin/tool/inspire/prediction.php', array('id' => $predictionid));
        $data->predictionurl = $predictionurl->out(false);

        $data->calculations = array();

        $calculations = $this->prediction->get_calculations();
        foreach ($calculations as $calculation) {

            // Hook for indicators with extra features that should not be displayed (e.g. discrete indicators).
            if (!$calculation->indicator->should_be_displayed($calculation->value, $calculation->subtype)) {
                continue;
            }

            $obj = new \stdClass();
            $obj->name = $calculation->indicator::get_name($calculation->subtype);
            if ($calculation->value !== null) {
                $obj->displayvalue = $calculation->indicator->get_display_value($calculation->value, $calculation->subtype);
                $obj->style = $calculation->indicator->get_value_style($calculation->value, $calculation->subtype);
            } else {
                // Null case is special, it does not represent a central value but a "can't be calculated".
                $obj->displayvalue = '';
                $obj->style = '';
            }
            $data->calculations[] = $obj;
        }

        // Targets have a last chance to add extra stuff, they decide on which template
        // predictions will be displayed, it is fair to give them powers to add extra
        // info for the template.
        //$data->prediction = $this->model->get_target()->add_extra_data_for_display($data->prediction);

        return $data;
    }
}
