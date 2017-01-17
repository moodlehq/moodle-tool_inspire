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
 * Inspire tool model representation.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 * Inspire tool model representation.
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

    /**
     * @var \tool_inspire\local\target\base
     */
    protected $target = null;

    /**
     * Unique Model id created from site info and last model modification time.
     *
     * It is the id that is passed to prediction processors so the same prediction
     * processor can be used for multiple moodle sites.
     *
     * @var string
     */
    protected $uniqueid = null;

    public function __construct($model) {
        $this->model = $model;
    }

    protected function get_target() {
        if ($this->target === null) {
            $classname = $this->model->target;
            $this->target = new $classname();
        }
        return $this->target;
    }

    public function get_indicators() {
        $fullclassnames = json_decode($this->model->indicators);

        if (!$fullclassnames || !is_array($fullclassnames)) {
            throw new \coding_exception('Model ' . $this->model->codename . ' indicators can not be read');
        }

        $indicators = array();
        foreach ($fullclassnames as $fullclassname) {
            $instance = \tool_inspire\manager::get_indicator($fullclassname);
            if ($instance) {
                $indicators[$fullclassname] = $instance;
            }
        }

        return $indicators;
    }

    public function get_analyser($options = array()) {

        $target = $this->get_target();
        $indicators = $this->get_indicators();

        if (!empty($options['evaluation'])) {
            // We try all available range processors.
            $rangeprocessors = \tool_inspire\manager::get_all_range_processors();
        } else {

            if (empty($this->model->rangeprocessor)) {
                throw new \moodle_exception('invalidrangeprocessor', 'tool_inspire', '', $this->model->codename);
            }

            // TODO This may get range processors from different moodle components.
            $fullclassname = '\\tool_inspire\\local\\range_processor\\' . $this->model->rangeprocessor;

            // Returned as an array in case we decide to allow multiple range processors enabled for a single model.
            $rangeprocessors = array(\tool_inspire\manager::get_range_processor($fullclassname));
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

        foreach (\tool_inspire\manager::get_all_range_processors() as $rangeprocessor) {

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
            $predictor = \tool_inspire\manager::get_predictions_processor();

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
            $predictor = \tool_inspire\manager::get_predictions_processor();

            // Train using the dataset.
            $predictorresult = $predictor->train($this->get_unique_id(), $filepath, $outputdir);

            $result->status = self::TRAIN_OK;
            $result->errors = $predictorresult->errors;

            $results[$rangeprocessorcodename] = $result;

            $this->flag_file_as_used($dataset, 'trained');
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

        foreach ($samplesdata['files'] as $rangeprocessorcodename => $samplesfile) {

            // From moodle filesystem to the file system.
            // TODO This is not ideal, but it seems that there is no read access to moodle filesystem files.
            $dir = make_request_directory();
            $filepath = $samplesfile->copy_content_to_temp($dir);

            $outputdir = $this->get_output_dir($rangeprocessorcodename);

            $predictor = \tool_inspire\manager::get_predictions_processor();
            $predictorresult = $predictor->predict($this->get_unique_id(), $filepath, $outputdir);

            $result = new \stdClass();
            $result->status = self::PREDICT_OK;
            $result->errors = $predictorresult->errors;
            $result->predictions = $predictorresult->predictions;

            foreach ($result->predictions as $sampleinfo) {

                switch (count($sampleinfo)) {
                    case 1:
                        // For whatever reason the predictions processor could not process this sample, we
                        // skip it and do nothing with it.
                        debugging($this->model->id . ' model predictions processor could not process the sample with id ' .
                            $sampleinfo[0], DEBUG_DEVELOPER);
                        continue;
                    case 2:
                        // Prediction processors that do not return a prediction score will have the maximum prediction
                        // score.
                        list($sampleid, $prediction) = $sampleinfo;
                        $predictionscore = 1;
                        break;
                    case 3:
                        list($sampleid, $prediction, $predictionscore) = $sampleinfo;
                        break;
                    default:
                        break;
                }

                // The prediction should be reliable enough according to how the model was designed.
                if (floatval($predictionscore) < floatval($this->model->predictionminscore)) {
                    continue;
                }

                if ($this->get_target()->triggers_callback($prediction)) {
                    $this->get_target()->callback($sampleid, $prediction);
                }
            }

            $this->flag_file_as_used($samplesfile, 'predicted');
        }
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

    protected function flag_file_as_used(\stored_file $file, $action) {
        global $DB;

        $usedfile = new \stdClass();
        $usedfile->modelid = $this->model->id;
        $usedfile->fileid = $file->get_id();
        $usedfile->action = $action;
        $usedfile->time = time();
        $DB->insert_record('tool_inspire_used_files', $usedfile);
    }
}
