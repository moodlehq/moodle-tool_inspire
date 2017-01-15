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
 * Inspire tool manager
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 * Inspire tool site manager.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model {

    const ANALYSE_OK = 0;
    const ANALYSE_GENERAL_ERROR = 1;
    const ANALYSE_INPROGRESS = 2;
    const ANALYSE_REJECTED_RANGE_PROCESSOR = 3;
    const ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS = 4;
    const ANALYSABLE_STATUS_INVALID_FOR_TARGET = 5;

    const EVALUATE_OK = 0;
    const EVALUATE_NO_DATASET = 1;

    const TRAIN_OK = 0;
    const TRAIN_NO_DATASET = 1;

    const PREDICT_OK = 0;

    protected $model = null;

    protected $uniqueid = null;

    public function __construct($model) {
        $this->model = $model;
    }

    protected function get_target() {
        $classname = $this->model->target;
        return new $classname();
    }

    protected function get_indicators() {

        $indicators = [];

        // TODO Read the model indicators instead of read all indicators in the folder.
        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\indicator');
        foreach ($classes as $fullclassname => $classpath) {

            // Discard abstract classes and others.
            if (is_subclass_of($fullclassname, 'tool_inspire\local\indicator\base')) {
                if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                    $indicators[$fullclassname] = new $fullclassname();
                }
            }
        }

        return $indicators;
    }

    public function get_analyser($options) {

        $target = $this->get_target();
        $indicators = $this->get_indicators();

        if (!empty($options['evaluation'])) {
            // We try all available range processors.
            $rangeprocessors = $this->get_all_range_processors();
        } else {

            if (empty($this->model->rangeprocessor)) {
                throw new \moodle_exception('invalidrangeprocessor', 'tool_inspire', '', $this->model->codename);
            }

            // TODO This may get range processors from different moodle components.
            $fullclassname = '\\tool_inspire\\local\\range_processor\\' . $this->model->rangeprocessor;

            // Returned as an array in case we decide to allow multiple range processors enabled for a single model.
            $rangeprocessors = array($this->get_range_processor($fullclassname));
        }

        if (empty($target)) {
            throw new \moodle_exception('errornotarget', 'tool_inspire');
        }

        if (empty($indicators)) {
            throw new \moodle_exception('errornoindicators', 'tool_inspire');
        }

        if (empty($rangeprocessors)) {
            throw new \moodle_exception('errornorangeprocessors', 'tool_inspire');
        }

        $classname = $target->get_analyser_class();
        if (!class_exists($classname)) {
            throw \coding_exception($classname . ' class does not exists');
        }

        // Returns a \tool_inspire\local\analyser\base class.
        return new $classname($this->model->id, $target, $indicators, $rangeprocessors, $options);
    }

    /**
     * Get all available range processors.
     *
     * @return \tool_inspire\range_processor\base[]
     */
    protected function get_all_range_processors() {

        // TODO: It should be able to search range processors in other plugins.
        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\range_processor');

        $rangeprocessors = [];
        foreach ($classes as $fullclassname => $classpath) {
            if (self::is_valid_range_processor($fullclassname)) {
                $instance = $this->get_range_processor($fullclassname);
                $rangeprocessors[$instance->get_codename()] = $instance;
            }
        }

        return $rangeprocessors;
    }

    protected function get_range_processor($fullclassname) {
        return new $fullclassname();
    }

    /**
     * is_valid_range_processor
     *
     * @param string $fullclassname
     * @return bool
     */
    protected static function is_valid_range_processor($fullclassname) {
        if (is_subclass_of($fullclassname, '\tool_inspire\local\range_processor\base')) {
            if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds the model dataset.
     *
     * @param  array   $options
     * @return array Status codes and generated files
     */
    public function build_dataset($options = array()) {
        $analyser = $this->get_analyser($options);
        return $analyser->get_labelled_data();
    }

    /**
     * Evaluates the model datasets.
     *
     * Model datasets should already be available in Moodle's filesystem.
     *
     * @return stdClass[]
     */
    public function evaluate($options) {

        $options['evaluation'] = true;
        $analysisresults = $this->build_dataset($options);

        foreach ($analysisresults['status'] as $analysableid => $statuscode) {
            mtrace('Analysable ' . $analysableid . ': Status code ' . $statuscode . '. ');
            if (!empty($analysisresults['messages'][$analysableid])) {
                mtrace(' - ' . $analysisresults['messages'][$analysableid]);
            }
        }

        $results = array();

        foreach ($this->get_all_range_processors() as $rangeprocessor) {

            $result = new \stdClass();

            $dataset = \tool_inspire\dataset_manager::get_evaluation_range_file($this->model->id, $rangeprocessor->get_codename());

            if (!$dataset) {

                $result->status = self::EVALUATE_NO_DATASET;
                $result->score = 0;
                $result->dataset = null;
                $result->errors = array('No dataset found');

                $results[$rangeprocessor->get_codename()] = $result;
                continue;
            }

            // From moodle filesystem to the file system.
            // TODO This is not ideal, but it seems that there is no read access to moodle filesystem files.
            $dir = make_request_directory();
            $filepath = $dataset->copy_content_to_temp($dir);

            // Copy the evaluated dataset filepath to the result object.
            $result->dataset = $filepath;

            $outputdir = $this->get_output_dir($rangeprocessor->get_codename());
            $predictor = $this->get_predictions_processor();

            // Evaluate the dataset.
            $predictorresult = $predictor->evaluate($this->model->id, $filepath, $outputdir);

            $result->status = self::EVALUATE_OK;
            $result->score = $predictorresult->score;
            $result->errors = $predictorresult->errors;

            $results[$rangeprocessor->get_codename()] = $result;
        }

        return $results;
    }

    public function train() {
        global $DB;

        if ($this->model->enabled == false || empty($this->model->rangeprocessor)) {
            throw new \moodle_exception('invalidrangeprocessor', 'tool_inspire', '', $this->model->codename);
        }

        $results = array();

        $analysed = $this->build_dataset();

        // No training if no files have been provided.
        if (empty($analysed['files'])) {
            $result = new \stdClass();
            $result->status = self::TRAIN_NO_DATASET;
            $result->errors = array('No files suitable for training') + $analysed['messages'];

            // Copy the result to all range processors.
            foreach ($analysed as $rangeprocessorcodename => $unused) {
                $results[$rangeprocessorcodename] = $result;
            }
            return $results;
        }

        foreach ($analysed['files'] as $rangeprocessorcodename => $dataset) {

            $result = new \stdClass();

            // From moodle filesystem to the file system.
            // TODO This is not ideal, but it seems that there is no read access to moodle filesystem files.
            $dir = make_request_directory();
            $filepath = $dataset->copy_content_to_temp($dir);

            $outputdir = $this->get_output_dir($rangeprocessorcodename);
            $predictor = $this->get_predictions_processor();

            // Train using the dataset.
            $predictorresult = $predictor->train($this->get_unique_id(), $filepath, $outputdir);

            $result->status = self::TRAIN_OK;
            $result->errors = $predictorresult->errors;

            $results[$rangeprocessorcodename] = $result;

            // TODO Mark the file as trained.
        }

        // Mark the model as trained if it wasn't.
        if ($this->model->trained == false) {
            $this->mark_as_trained();
        }

        return $results;
    }

    public function predict() {

        if ($this->model->enabled == false || empty($this->model->rangeprocessor)) {
            throw new \moodle_exception('invalidrangeprocessor', 'tool_inspire', '', $this->model->codename);
        }

        $analyser = $this->get_analyser();
        $samplesdata = $analyser->get_unlabelled_data();

        $results = array();

        foreach ($samplesdata['files'] as $rangeprocessorcodename => $samples) {

            // From moodle filesystem to the file system.
            // TODO This is not ideal, but it seems that there is no read access to moodle filesystem files.
            $dir = make_request_directory();
            $filepath = $samples->copy_content_to_temp($dir);

            $outputdir = $this->get_output_dir($rangeprocessorcodename);

            $predictor = $this->get_predictions_processor();
            $predictorresult = $predictor->predict($this->get_unique_id(), $filepath);

            $result = new \stdClass();
            $result->status = self::PREDICT_OK;
            $result->errors = $predictorresult->errors;
            $result->predictions = $predictorresult->predictions;

            $results[$rangeprocessorcodename] = $result;

            // TODO Mark the file as predicted.
        }
var_dump($results);
    }

    public function enable($rangeprocessorcodename = false) {
        global $DB;

        if ($rangeprocessorcodename) {
            $this->model->rangeprocessor = $rangeprocessorcodename;
            $this->model->timemodified = time();
        }
        $this->model->enabled = 1;

        // We don't always update timemodified intentionally as we reserve it for target, indicators or rangeprocessor updates.
        $DB->update_record('tool_inspire_models', $this->model);
    }

    public function mark_as_trained() {
        global $DB;

        $this->model->trained = 1;
        $DB->update_record('tool_inspire_models', $this->model);
    }

    protected function get_predictions_processor() {
        // TODO Select it based on a config setting.
        return new \predict_python\processor();
        //return new \predict_php\processor();
    }

    protected function get_output_dir($subdir = false) {
        global $CFG;

        $outputdir = get_config('tool_inspire', 'modeloutputdir');
        if (empty($outputdir)) {
            // Apply default value.
            $outputdir = rtrim($CFG->dataroot, '/') . DIRECTORY_SEPARATOR . 'models';
        }

        $outputdir = $outputdir . DIRECTORY_SEPARATOR . $this->get_unique_id();

        if ($subdir) {
            $outputdir = $outputdir . DIRECTORY_SEPARATOR . $subdir;
        }

        if (!is_dir($outputdir)) {
            mkdir($outputdir, $CFG->directorypermissions, true);
        }

        return $outputdir;
    }

    protected function get_unique_id() {
        global $CFG;

        if (!is_null($this->uniqueid)) {
            return $this->uniqueid;
        }

        // Generate a unique id for this site, this model and this range processor, considering the last time
        // that the model target and indicators were updated.
        $ids = array($CFG->wwwroot, $CFG->dirroot, $CFG->prefix, $this->model->id, $this->model->timemodified);
        $this->uniqueid = sha1(implode('$$', $ids));

        return $this->uniqueid;
    }
}
