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
require_once(__DIR__ . '/fixtures/test_indicator_random.php');
require_once(__DIR__ . '/fixtures/test_target_shortname.php');

/**
 * Unit tests for evaluation, training and prediction.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_inspire_prediction_testcase extends advanced_testcase {

    /**
     * @dataProvider provider_training_and_prediction
     * @param string $timesplittingcodename
     * @param int $npredictedranges
     * @return void
     */
    public function test_training_and_prediction($timesplittingcodename, $npredictedranges, $predictionsprocessorclass) {
        global $DB;

        $ncourses = 10;

        $this->resetAfterTest(true);

        // Generate training data.
        $params = array(
            'startdate' => mktime(0, 0, 0, 10, 24, 2015),
            'enddate' => mktime(0, 0, 0, 2, 24, 2016),
        );
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'a' . random_string(10);
            $courseparams = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($courseparams);
        }
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'b' . random_string(10);
            $courseparams = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($courseparams);
        }

        $predictionsprocessor = \tool_inspire\manager::get_predictions_processor($predictionsprocessorclass);
        $predictionprocessors = \tool_inspire\manager::get_all_prediction_processors();

        set_config('predictionsprocessor', $predictionsprocessorclass, 'tool_inspire');

        $modelobj = $this->add_perfect_model();
        $model = new \tool_inspire\model($modelobj);
        $model->enable($timesplittingcodename);

        // No samples trained yet.
        $this->assertEquals(0, $DB->count_records('tool_inspire_train_samples', array('modelid' => $model->get_id())));

        $results = $model->train();
        $this->assertEquals(1, $model->get_model_obj()->enabled);
        $this->assertEquals(1, $model->get_model_obj()->trained);

        // 1 training file was created.
        $trainedsamples = $DB->get_records('tool_inspire_train_samples', array('modelid' => $model->get_id()));
        $this->assertEquals(1, count($trainedsamples));
        $samples = json_decode(reset($trainedsamples)->sampleids, true);
        $this->assertEquals($ncourses * 2, count($samples));
        $this->assertEquals(1, $DB->count_records('tool_inspire_used_files',
            array('modelid' => $model->get_id(), 'action' => 'trained')));

        // Now we create 2 hidden courses (they should not be used for training by the target).
        $courseparams = $params + array('shortname' => 'aaaaaa', 'fullname' => 'aaaaaa', 'visible' => 0);
        $course1 = $this->getDataGenerator()->create_course($courseparams);
        $courseparams = $params + array('shortname' => 'bbbbbb', 'fullname' => 'bbbbbb', 'visible' => 0);
        $course2 = $this->getDataGenerator()->create_course($courseparams);

        // No more files should be created as the 2 new courses should be skipped by the target (not ready for training).
        $results = $model->train();
        $trainedsamples = $DB->get_records('tool_inspire_train_samples', array('modelid' => $model->get_id()));
        $this->assertEquals(1, count($trainedsamples));
        $this->assertEquals(1, $DB->count_records('tool_inspire_used_files',
            array('modelid' => $model->get_id(), 'action' => 'trained')));

        // They will not be skipped for prediction though.
        $result = $model->predict();

        // $course1 predictions should be 1 == 'a', $course2 predictions should be 0 == 'b'.
        $correct = array($course1->id => 1, $course2->id => 0);
        foreach ($result->predictions as $sampleprediction) {
            list($sampleid, $prediction) = $sampleprediction;
            list($sampleid, $rangeindex) = $model->get_time_splitting()->infer_sample_info($sampleid);

            // The range index is not important here, both ranges prediction will be the same.
            $this->assertEquals($correct[$sampleid], $prediction);
        }

        // 2 ranges will be predicted.
        $trainedsamples = $DB->get_records('tool_inspire_predict_ranges', array('modelid' => $model->get_id()));
        $this->assertEquals($npredictedranges, count($trainedsamples));
        $this->assertEquals(1, $DB->count_records('tool_inspire_used_files',
            array('modelid' => $model->get_id(), 'action' => 'predicted')));
        // 2 predictions for each range.
        $this->assertEquals(2 * $npredictedranges, $DB->count_records('tool_inspire_predictions', array('modelid' => $model->get_id())));

        // No new generated files nor records as there are no new courses available.
        $model->predict();
        $trainedsamples = $DB->get_records('tool_inspire_predict_ranges', array('modelid' => $model->get_id()));
        $this->assertEquals($npredictedranges, count($trainedsamples));
        $this->assertEquals(1, $DB->count_records('tool_inspire_used_files',
            array('modelid' => $model->get_id(), 'action' => 'predicted')));
        $this->assertEquals(2 * $npredictedranges, $DB->count_records('tool_inspire_predictions', array('modelid' => $model->get_id())));
    }

    public function provider_training_and_prediction() {
        $cases = array(
            'no_splitting' => array('no_splitting', 1),
            'quarters' => array('quarters', 2)
        );

        // We need to test all system prediction processors.
        return $this->add_prediction_processors($cases);
    }


    /**
     * Basic test to check that prediction processors work as expected.
     *
     * @dataProvider provider_test_evaluation
     */
    public function test_evaluation($modelquality, $ncourses, $expected, $predictionsprocessorclass) {
        $this->resetAfterTest(true);

        set_config('timesplittings', 'weekly,single_range,quarters', 'tool_inspire');

        if ($modelquality === 'perfect') {
            $modelobj = $this->add_perfect_model();
        } else if ($modelquality === 'random') {
            $modelobj = $this->add_random_model();
        } else {
            throw new \coding_exception('Only perfect and random accepted as $modelquality values');
        }


        // Generate training data.
        $params = array(
            'startdate' => mktime(0, 0, 0, 10, 24, 2015),
            'enddate' => mktime(0, 0, 0, 2, 24, 2016),
        );
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'a' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'b' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }

        // We repeat the test for all prediction processors.
        $predictionsprocessor = \tool_inspire\manager::get_predictions_processor($predictionsprocessorclass);

        set_config('predictionsprocessor', $predictionsprocessorclass, 'tool_inspire');

        $model = new \tool_inspire\model($modelobj);
        $results = $model->evaluate();

        // We check that the returned status includes at least $expectedcode code.
        foreach ($results as $timesplitting => $result) {
            $message = 'The returned status code should include ' . $expected[$timesplitting] . ', ' .
                $result->status . ' returned';
            $this->assertEquals($expected[$timesplitting], $result->status & $expected[$timesplitting], $message);
        }
    }

    public function provider_test_evaluation() {

        $cases = array(
            'bad-and-no-enough-data' => array(
                'modelquality' => 'random',
                'ncourses' => 10,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    // 10 samples is not enough to process anything.
                    'single_range' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                )
            ),
            'bad' => array(
                'modelquality' => 'random',
                'ncourses' => 50,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'single_range' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                )
            ),
            'good' => array(
                'modelquality' => 'perfect',
                'ncourses' => 50,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'single_range' => \tool_inspire\model::OK,
                    'quarters' => \tool_inspire\model::OK,
                )
            )
        );
        return $this->add_prediction_processors($cases);
    }

    protected function add_random_model() {
        global $DB, $USER;

        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_random');

        $modelobj = new stdClass();
        $modelobj->target = 'test_target_shortname';
        $modelobj->indicators = json_encode($indicators);
        $modelobj->evaluationminscore = 0.7;
        $modelobj->timecreated = time();
        $modelobj->timemodified = time();
        $modelobj->usermodified = $USER->id;
        $id = $DB->insert_record('tool_inspire_models', $modelobj);

        // To load db defaults as well.
        return $DB->get_record('tool_inspire_models', array('id' => $id));
    }

    protected function add_perfect_model() {
        global $DB, $USER;

        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_fullname');

        $modelobj = new stdClass();
        $modelobj->target = 'test_target_shortname';
        $modelobj->indicators = json_encode($indicators);
        $modelobj->evaluationminscore = 0.7;
        $modelobj->timecreated = time();
        $modelobj->timemodified = time();
        $modelobj->usermodified = $USER->id;
        $id = $DB->insert_record('tool_inspire_models', $modelobj);

        // To load db defaults as well.
        return $DB->get_record('tool_inspire_models', array('id' => $id));
    }

    protected function add_prediction_processors($cases) {

        $return = array();

        // We need to test all system prediction processors.
        $predictionprocessors = \tool_inspire\manager::get_all_prediction_processors();
        foreach ($predictionprocessors as $classfullname => $unused) {
            foreach ($cases as $key => $case) {
                $newkey = $key . '-' . $classfullname;
                $return[$newkey] = $case + array('predictionsprocessorclass' => $classfullname);
            }
        }

        return $return;
    }
}
