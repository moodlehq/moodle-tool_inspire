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
 * Unit tests for the model.
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
 * Unit tests for the model.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_inspire_model_testcase extends advanced_testcase {

    public function setUp() {

        $target = \tool_inspire\manager::get_target('test_target_shortname');
        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_fullname');
        foreach ($indicators as $key => $indicator) {
            $indicators[$key] = \tool_inspire\manager::get_indicator($indicator);
        }

        $this->model = testable_model::create($target, $indicators);
        $this->modelobj = $this->model->get_model_obj();
    }

    public function test_enable() {
        $this->resetAfterTest(true);

        $this->assertEquals(0, $this->model->get_model_obj()->enabled);
        $this->assertEquals(0, $this->model->get_model_obj()->trained);
        $this->assertEquals('', $this->model->get_model_obj()->timesplitting);

        $this->model->enable('\\tool_inspire\\local\\time_splitting\\quarters');
        $this->assertEquals(1, $this->model->get_model_obj()->enabled);
        $this->assertEquals(0, $this->model->get_model_obj()->trained);
        $this->assertEquals('\\tool_inspire\\local\\time_splitting\\quarters', $this->model->get_model_obj()->timesplitting);
    }

    public function test_create() {
        $this->resetAfterTest(true);

        $target = \tool_inspire\manager::get_target('\tool_inspire\local\target\course_dropout');
        $indicators = array(
            \tool_inspire\manager::get_indicator('\tool_inspire\local\indicator\any_write_action'),
            \tool_inspire\manager::get_indicator('\tool_inspire\local\indicator\read_actions')
        );
        $model = \tool_inspire\model::create($target, $indicators);
        $this->assertInstanceOf('\tool_inspire\model', $model);
    }

    public function test_model_manager() {
        $this->resetAfterTest(true);

        $this->assertCount(3, $this->model->get_indicators());
        $this->assertInstanceOf('\tool_inspire\local\target\binary', $this->model->get_target());

        // Using evaluation as the model is not yet enabled.
        $this->model->init_analyser(array('evaluation' => true));
        $this->assertInstanceOf('\tool_inspire\local\analyser\base', $this->model->get_analyser());

        $this->model->enable('\tool_inspire\local\time_splitting\quarters');
        $this->assertInstanceOf('\tool_inspire\local\analyser\courses', $this->model->get_analyser());
    }

    public function test_output_dir() {
        $this->resetAfterTest(true);

        $dir = make_request_directory();
        set_config('modeloutputdir', $dir, 'tool_inspire');

        $modeldir = $dir . DIRECTORY_SEPARATOR . $this->modelobj->id . DIRECTORY_SEPARATOR . $this->modelobj->version . DIRECTORY_SEPARATOR;
        $this->assertEquals($modeldir, $this->model->get_output_dir());
        $this->assertEquals($modeldir . DIRECTORY_SEPARATOR . 'asd', $this->model->get_output_dir(array('asd')));
    }

    public function test_unique_id() {
        global $DB;

        $this->resetAfterTest(true);

        $originaluniqueid = $this->model->get_unique_id();

        // Same id across instances.
        $this->model = new testable_model($this->modelobj);
        $this->assertEquals($originaluniqueid, $this->model->get_unique_id());

        // We will restore it later.
        $originaltimemodified = $this->modelobj->timemodified;

        // Generates a different id if timemodified changes.
        $this->modelobj->timemodified = time() + 1;
        $DB->update_record('tool_inspire_models', $this->modelobj);
        $this->model = new testable_model($this->modelobj);
        $this->assertNotEquals($originaluniqueid, $this->model->get_unique_id());

        // Restore original timemodified to continue testing.
        $this->modelobj->timemodified = $originaltimemodified;
        $DB->update_record('tool_inspire_models', $this->modelobj);
        // Same when updating through an action that changes the model.
        $this->model = new testable_model($this->modelobj);

        $this->model->mark_as_trained();
        $this->assertEquals($originaluniqueid, $this->model->get_unique_id());

        $this->model->enable();
        $this->assertEquals($originaluniqueid, $this->model->get_unique_id());

        $this->model->enable('\tool_inspire\local\time_splitting\quarters');
        $this->assertEquals($originaluniqueid, $this->model->get_unique_id());

    }
}

class testable_model extends \tool_inspire\model {
    public function get_output_dir($subdirs = array()) {
        return parent::get_output_dir($subdirs);
    }

    public function init_analyser($options = array()) {
        return parent::init_analyser($options);
    }
}
