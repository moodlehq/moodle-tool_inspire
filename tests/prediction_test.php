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
 * Unit tests for evaluation, training and prediction.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_indicator_max.php');
require_once(__DIR__ . '/fixtures/test_indicator_min.php');
require_once(__DIR__ . '/fixtures/test_indicator_fullname.php');
require_once(__DIR__ . '/fixtures/test_target_shortname.php');

/**
 * Unit tests for evaluation, training and prediction.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_inspire_prediction_testcase extends advanced_testcase {

    public function setUp() {
        global $DB, $USER;

        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_fullname');
        //$indicators = array('test_indicator_fullname');

        $this->modelobj = new stdClass();
        $this->modelobj->codename = 'testmodel';
        $this->modelobj->target = 'test_target_shortname';
        $this->modelobj->indicators = json_encode($indicators);
        $this->modelobj->predictionminscore = 0.8;
        $this->modelobj->timecreated = time();
        $this->modelobj->timemodified = time();
        $this->modelobj->usermodified = $USER->id;
        $id = $DB->insert_record('tool_inspire_models', $this->modelobj);

        // To load db defaults as well.
        $this->modelobj = $DB->get_record('tool_inspire_models', array('id' => $id));
    }

    public function test_training() {
        $this->resetAfterTest(true);
    }

    public function test_prediction() {
        $this->resetAfterTest(true);
    }

    public function test_evaluation() {
        $this->resetAfterTest(true);

        // Generate training data.
        $params = array(
            'startdate' => mktime(0, 0, 0, 10, 24, 2015),
            'enddate' => mktime(0, 0, 0, 2, 24, 2016),
        );
        for ($i = 0; $i < 50; $i++) {
            $name = 'a' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }
        for ($i = 0; $i < 50; $i++) {
            $name = 'b' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }

        // We repeat the test for all enabled prediction processors.
        // TODO The "enabled" part is not implemented"
        $predictionprocessors = \tool_inspire\manager::get_all_prediction_processors();
        // TODO Uncomment.
        unset($predictionprocessors['\\predict_php\\processor']);

        foreach ($predictionprocessors as $classfullname => $predictionsprocessor) {

            set_config('predictionprocessor', $classfullname, 'tool_inspire');

            $this->model = new \tool_inspire\model($this->modelobj);
            $results = $this->model->evaluate();

            $this->assertEquals(\tool_inspire\model::NO_DATASET, $results['weekly']->status);
            $this->assertEquals(\tool_inspire\model::NO_DATASET, $results['weekly_accum']->status);
            $this->assertEquals(\tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA, $results['no_range']->status);
            $this->assertEquals(\tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA, $results['single_range']->status);
            $this->assertEquals(\tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA, $results['quarters']->status);
            $this->assertEquals(\tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA, $results['quarters_accum']->status);
        }

    }
}
