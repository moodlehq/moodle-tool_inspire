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

    const OK = 0;
    const GENERAL_ERROR = 1;
    const NO_DATASET = 2;

    const EVALUATE_LOW_SCORE = 4;
    const EVALUATE_NOT_ENOUGH_DATA = 8;

    const ANALYSE_INPROGRESS = 2;
    const ANALYSE_REJECTED_RANGE_PROCESSOR = 4;
    const ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS = 8;
    const ANALYSABLE_STATUS_INVALID_FOR_TARGET = 16;

    protected $model = null;

    /**
     * @var \tool_inspire\local\target\base
     */
    protected $target = null;

    /**
     * @var \tool_inspire\local\indicator\base[]
     */
    protected $indicators = null;

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

    public function get_id() {
        return $this->model->id;
    }

    public function get_model_obj() {
        return $this->model;
    }

    public function get_target() {
        if ($this->target !== null) {
            return $this->target;
        }
        $classname = $this->model->target;
        $this->target = new $classname();

        return $this->target;
    }

    public function get_indicators() {
        if ($this->indicators !== null) {
            return $this->indicators;
        }

        $fullclassnames = json_decode($this->model->indicators);

        if (!$fullclassnames || !is_array($fullclassnames)) {
            throw new \coding_exception('Model ' . $this->model->id . ' indicators can not be read');
        }

        $this->indicators = array();
        foreach ($fullclassnames as $fullclassname) {
            $instance = \tool_inspire\manager::get_indicator($fullclassname);
            if ($instance) {
                $this->indicators[$fullclassname] = $instance;
            }
        }

        return $this->indicators;
    }

    public function get_analyser($options = array()) {

        $target = $this->get_target();
        $indicators = $this->get_indicators();

        if (!empty($options['evaluation'])) {
            // We try all available time splitting methods.
            $timesplittings = \tool_inspire\manager::get_enabled_time_splitting_methods();
        } else {

            if (empty($this->model->timesplitting)) {
                throw new \moodle_exception('invalidtimesplitting', 'tool_inspire', '', $this->model->id);
            }

            // TODO This may get time splitting methods from different moodle components.
            $fullclassname = '\\tool_inspire\\local\\time_splitting\\' . $this->model->timesplitting;

            // Returned as an array in case we decide to allow multiple time splitting methods enabled for a single model.
            $timesplittings = array(\tool_inspire\manager::get_time_splitting($fullclassname));
        }

        if (empty($target)) {
            throw new \moodle_exception('errornotarget', 'tool_inspire');
        }

        if (empty($indicators)) {
            throw new \moodle_exception('errornoindicators', 'tool_inspire');
        }

        if (empty($timesplittings)) {
            throw new \moodle_exception('errornotimesplittings', 'tool_inspire');
        }

        $classname = $target->get_analyser_class();
        if (!class_exists($classname)) {
            throw \coding_exception($classname . ' class does not exists');
        }

        // Returns a \tool_inspire\local\analyser\base class.
        return new $classname($this->model->id, $target, $indicators, $timesplittings, $options);
    }

    /**
     * Evaluates the model datasets.
     *
     * Model datasets should already be available in Moodle's filesystem.
     *
     * @return stdClass[]
     */
    public function evaluate($options = array()) {

        $options['evaluation'] = true;
        $analysisresults = $this->get_analyser($options)->get_labelled_data();

        // Yeah, a bit hacky, but we are really interested in these messages when running evaluation in CLI so we know why
        // a course is not suitable.
        if (!PHPUNIT_TEST) {
            foreach ($analysisresults['status'] as $analysableid => $statuscode) {
                mtrace('Analysable ' . $analysableid . ': Status code ' . $statuscode . '. ');
                if (!empty($analysisresults['messages'][$analysableid])) {
                    mtrace(' - ' . $analysisresults['messages'][$analysableid]);
                }
            }
        }

        $results = array();

        foreach (\tool_inspire\manager::get_enabled_time_splitting_methods() as $timesplitting) {

            $result = new \stdClass();

            $dataset = \tool_inspire\dataset_manager::get_evaluation_file($this->model->id, $timesplitting->get_codename());

            if (!$dataset) {

                $result->status = self::NO_DATASET;
                $result->score = 0;
                $result->errors = array('Was not possible to create a dataset for this time splitting method');

                $results[$timesplitting->get_codename()] = $result;
                continue;
            }

            $outputdir = $this->get_output_dir($timesplitting->get_codename());
            $predictor = \tool_inspire\manager::get_predictions_processor();

            // Evaluate the dataset, the deviation we accept in the results depends on the amount of iterations.
            $resultsdeviation = 0.02;
            $niterations = 100;
            $predictorresult = $predictor->evaluate($this->model->id, $this->model->evaluationminscore,
                $resultsdeviation, $niterations, $dataset, $outputdir);

            $result->status = $predictorresult->status;
            $result->score = $predictorresult->score;
            $result->errors = $predictorresult->errors;

            $results[$timesplitting->get_codename()] = $result;
        }

        return $results;
    }

    public function train() {
        global $DB;

        if ($this->model->enabled == false || empty($this->model->timesplitting)) {
            throw new \moodle_exception('invalidtimesplitting', 'tool_inspire', '', $this->model->id);
        }

        $results = array();

        $analysed = $this->get_analyser()->get_labelled_data();

        // No training if no files have been provided.
        if (empty($analysed['files'])) {
            $result = new \stdClass();
            $result->status = self::NO_DATASET;
            $result->errors = array('No files suitable for training') + $analysed['messages'];

            // Copy the result to all time splitting methods.
            foreach ($analysed as $timesplittingcodename => $unused) {
                $results[$timesplittingcodename] = $result;
            }
            return $results;
        }

        foreach ($analysed['files'] as $timesplittingcodename => $dataset) {

            $result = new \stdClass();

            $outputdir = $this->get_output_dir($timesplittingcodename);
            $predictor = \tool_inspire\manager::get_predictions_processor();

            // Train using the dataset.
            $predictorresult = $predictor->train($this->get_unique_id(), $dataset, $outputdir);

            $result->status = $predictorresult->status;
            $result->errors = $predictorresult->errors;

            $results[$timesplittingcodename] = $result;

            $this->flag_file_as_used($dataset, 'trained');
        }

        // Mark the model as trained if it wasn't.
        if ($this->model->trained == false) {
            $this->mark_as_trained();
        }

        return $results;
    }

    public function predict() {
        global $DB;

        if ($this->model->enabled == false || empty($this->model->timesplitting)) {
            throw new \moodle_exception('invalidtimesplitting', 'tool_inspire', '', $this->model->id);
        }

        $samplesdata = $this->get_analyser()->get_unlabelled_data();

        foreach ($samplesdata['files'] as $timesplittingcodename => $samplesfile) {

            // We need to throw an exception if we are trying to predict stuff that was already predicted.
            $params = array('fileid' => $samplesfile->get_id(), 'action' => 'predicted', 'modelid' => $this->model->id);
            if ($predicted = $DB->get_record('tool_inspire_used_files', $params)) {
                throw new \moodle_exception('erroralreadypredict', 'tool_inspire', '', $samplesfile->get_id());
            }

            $outputdir = $this->get_output_dir($timesplittingcodename);

            $predictor = \tool_inspire\manager::get_predictions_processor();
            $predictorresult = $predictor->predict($this->get_unique_id(), $samplesfile, $outputdir);

            $result = new \stdClass();
            $result->status = $predictorresult->status;
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

                if ($this->get_target()->triggers_callback($prediction, $predictionscore)) {
                    $this->get_target()->callback($sampleid, $prediction, $predictionscore);
                }
            }

            $this->flag_file_as_used($samplesfile, 'predicted');
        }
    }

    public function enable($timesplittingcodename = false) {
        global $DB;

        if ($timesplittingcodename) {
            $this->model->timesplitting = $timesplittingcodename;
            $this->model->timemodified = time();
        }
        $this->model->enabled = 1;

        // We don't always update timemodified intentionally as we reserve it for target, indicators or timesplitting updates.
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

    public function get_unique_id() {
        global $CFG;

        if (!is_null($this->uniqueid)) {
            return $this->uniqueid;
        }

        // Generate a unique id for this site, this model and this time splitting method, considering the last time
        // that the model target and indicators were updated.
        $ids = array($CFG->wwwroot, $CFG->dirroot, $CFG->prefix, $this->model->id, $this->model->timemodified);
        $this->uniqueid = sha1(implode('$$', $ids));

        return $this->uniqueid;
    }

    public function export() {
        $data = clone $this->model;
        $data->target = $this->get_target()->get_name();
        $data->indicators = array();
        foreach ($this->get_indicators() as $indicator) {
            $data->indicators[] = $indicator->get_name();
        }
        return $data;
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
