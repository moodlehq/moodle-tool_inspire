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
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    protected $modelid;

    protected $target;
    protected $indicators;
    protected $rangeprocessors;

    protected $options;

    public function __construct($modelid, \tool_inspire\local\target\base $target, $indicators, $rangeprocessors, $options) {
        $this->modelid = $modelid;
        $this->target = $target;
        $this->indicators = $indicators;
        $this->rangeprocessors = $rangeprocessors;

        if (empty($options['evaluation'])) {
            $options['evaluation'] = false;
        }
        $this->options = $options;

        // Checks if the analyser satisfies the indicators requirements.
        $this->check_indicators_requirements();
    }

    /**
     * This function is used to check calculables needs against the info provided in the analyser samples.
     *
     * @return string[]
     */
    abstract protected function samples_info();

    /**
     * This function returns the list of samples that can be calculated.
     *
     * @param \tool_inspire\analysable $analysable
     * @return array
     */
    abstract public function get_all_samples(\tool_inspire\analysable $analysable);

    /**
     * Main analyser method which processes the site analysables.
     *
     * \tool_inspire\local\analyser\by_course and \tool_inspire\local\analyser\sitewide are implementing
     * this method returning site courses (by_course) and the whole system (sitewide) as analysables.
     * In most of the cases you should have enough extending from one of these classes so you don't need
     * to reimplement this method.
     *
     * @return array Array containing a status codes for each analysable and a list of files, one for each range processor
     */
    abstract public function get_analysable_data($includetarget);

    public function get_labelled_data() {
        return $this->get_analysable_data(true);
    }

    public function get_unlabelled_data() {
        return $this->get_analysable_data(false);
    }

    /**
     * Checks if the analyser satisfies all the model indicators requirements.
     *
     * @throws requirements_exception
     * @return void
     */
    protected function check_indicators_requirements() {

        $samplesinfo = $this->samples_info();

        foreach ($this->indicators as $indicator) {
            foreach ($indicator::get_requirements() as $requirement) {
                if (empty($samplesinfo[$requirement])) {
                    throw new \tool_inspire\requirements_exception($indicator->get_codename() . ' indicator requires ' .
                        $requirement . ' which is not provided by ' . get_class($this));
                }
            }
        }
    }

    /**
     * Processes an analysable
     *
     * This method returns the general analysable status and an array of files by range processor
     * all error & status reporting at analysable + range processor level should not be returned
     * but shown, through mtrace(), debugging() or through exceptions depending on the case.
     *
     * @param \tool_inspire\analysable $analysable
     * @param bool $includetarget
     * @return array Analysable general status code AND (files by range processor OR error code)
     */
    public function process_analysable($analysable, $includetarget) {

        // Default returns.
        $files = array();
        $message = null;

        // We need to check that the analysable is valid for the target even if we don't include targets
        // as we still need to discard invalid analysables for the target.
        $result = $this->target->is_valid_analysable($analysable);
        if ($result !== true) {
            return [
                \tool_inspire\model::ANALYSABLE_STATUS_INVALID_FOR_TARGET,
                array(),
                $result
            ];
        }

        // Process all provided range processors.
        $results = array();
        foreach ($this->rangeprocessors as $rangeprocessor) {
            $result = $this->process_range($rangeprocessor, $analysable, $includetarget);
            if (!empty($result->file)) {
                $files[$rangeprocessor->get_codename()] = $result->file;
            }
            $results[] = $result;
        }

        // Set the status code.
        if (!empty($files)) {
            $status = \tool_inspire\model::ANALYSE_OK;
        } else {
            if (count($this->rangeprocessors) === 1) {
                // We can be more specific.
                $status = $results[0]->status;
                $message = $results[0]->message;
            } else {
                $status = \tool_inspire\model::ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS;
                $message = 'Analysable not valid for any of the range processors';
            }
        }

        return [
            $status,
            $files,
            $message
        ];
    }

    protected function process_range($rangeprocessor, $analysable, $includetarget) {

        mtrace($rangeprocessor->get_codename() . ' analysing analysable with id ' . $analysable->get_id());

        $result = new \stdClass();

        $rangeprocessor->set_analysable($analysable);
        if (!$rangeprocessor->is_valid_analysable()) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'Invalid analysable for this processor';
            return $result;
        }

        // What is a sample is defined by the analyser, it can be an enrolment, a course, a user, a question
        // attempt... it is on what we will base indicators calculations.
        $samples = $this->get_all_samples($analysable);

        if (count($samples) === 0) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'No data available';
            return $result;
        }

        if ($includetarget) {
            // All ranges are used when we are calculating data for training.
            $ranges = $rangeprocessor->get_all_ranges();
        } else {
            // Only some ranges can be used for prediction (it depends on the time range where we are right now).
            $ranges = $this->get_prediction_ranges($rangeprocessor);
        }

        // There is no need to keep track of the evaluated samples and ranges as we always evaluate the whole dataset.
        if ($this->options['evaluation'] === false) {

            if (empty($ranges)) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = 'No new data available';
                return $result;
            }

            // We skip all samples that are already part of a training dataset, even if they have noe been used for training yet.
            $samples = $this->filter_out_train_samples($samples, $analysable, $rangeprocessor);

            if (count($samples) === 0) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = 'No new data available';
                return $result;
            }

            // Only when processing data for predictions.
            if ($includetarget === false) {
                // We also filter out ranges that have already been used for predictions.
                $ranges = $this->filter_out_prediction_ranges($ranges, $analysable, $rangeprocessor);
            }

            if (count($ranges) === 0) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = 'No new ranges to process';
                return $result;
            }
        }

        $rangeprocessor->set_samples($samples);

        $dataset = new \tool_inspire\dataset_manager($this->modelid, $analysable->get_id(), $rangeprocessor->get_codename(),
            $this->options['evaluation'], $includetarget);

        // Flag the model + analysable + rangeprocessor as being analysed (prevent concurrent executions).
        $dataset->init_process();

        // Here we start the memory intensive process that will last until $data var is
        // unset (until the method is finished basically).
        if ($includetarget) {
            $data = $rangeprocessor->calculate($this->indicators, $ranges, $this->target);
        } else {
            $data = $rangeprocessor->calculate($this->indicators, $ranges);
        }

        if (!$data) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'No valid data available';
            return $result;
        }

        // Write all calculated data to a file.
        $file = $dataset->store($data);

        // Flag the model + analysable + rangeprocessor as analysed.
        $dataset->close_process();

        // No need to keep track of analysed stuff when evaluating.
        if ($this->options['evaluation'] === false) {
            // Save the samples that have been already analysed so they are not analysed again in future.

            if ($includetarget) {
                $this->save_train_samples($samples, $analysable, $rangeprocessor, $file);
            } else {
                $this->save_prediction_ranges($ranges, $analysable, $rangeprocessor);
            }
        }

        $result->status = \tool_inspire\model::ANALYSE_OK;
        $result->message = 'Successfully analysed';
        $result->file = $file;
        return $result;
    }

    protected function get_prediction_ranges($rangeprocessor) {

        $now = time();

        // We already provided the analysable to the range processor, there is no need to feed it back.
        $predictionranges = array();
        foreach ($rangeprocessor->get_all_ranges() as $rangeindex => $range) {
            if ($rangeprocessor->ready_to_predict($range)) {
                // We need to maintain the same indexes.
                $predictionranges[$rangeindex] = $range;
            }
        }

        return $predictionranges;
    }

    protected function filter_out_train_samples($samples, $analysable, $rangeprocessor) {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $analysable->get_id(),
            'rangeprocessor' => $rangeprocessor->get_codename());

        $trainingsamples = $DB->get_records('tool_inspire_train_samples', $params);

        // Skip each file trained samples.
        foreach ($trainingsamples as $trainingfile) {

            $usedsamples = json_decode($trainingfile->sampleids, true);

            if (!empty($usedsamples)) {
                // Reset $samples to $samples minus this file's $usedsamples.
                $samples = array_diff_key($samples, $usedsamples);
            }
        }

        return $samples;
    }

    protected function filter_out_prediction_ranges($ranges, $analysable, $rangeprocessor) {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $analysable->get_id(),
            'rangeprocessor' => $rangeprocessor->get_codename());

        $predictedranges = $DB->get_records('tool_inspire_predict_ranges', $params);
        foreach ($predictedranges as $predictedrange) {
            if (!empty($ranges[$predictedrange->rangeindex])) {
                unset($ranges[$predictedrange->rangeindex]);
            }
        }

        return $ranges;

    }

    protected function save_train_samples($samples, $analysable, $rangeprocessor, $file) {
        global $DB;

        $trainingsamples = new \stdClass();
        $trainingsamples->modelid = $this->modelid;
        $trainingsamples->analysableid = $analysable->get_id();
        $trainingsamples->rangeprocessor = $rangeprocessor->get_codename();
        $trainingsamples->fileid = $file->get_id();

        // TODO We just need the keys, we can save some space by removing the values.
        $trainingsamples->sampleids = json_encode($samples);
        $trainingsamples->timecreated = time();

        return $DB->insert_record('tool_inspire_train_samples', $trainingsamples);
    }

    protected function save_prediction_ranges($ranges, $analysable, $rangeprocessor) {
        global $DB;

        $predictionrange = new \stdClass();
        $predictionrange->modelid = $this->modelid;
        $predictionrange->analysableid = $analysable->get_id();
        $predictionrange->rangeprocessor = $rangeprocessor->get_codename();
        $predictionrange->timecreated = time();

        foreach ($ranges as $rangeindex => $unused) {
            $predictionrange->rangeindex = $rangeindex;
            $DB->insert_record('tool_inspire_predict_ranges', $predictionrange);
        }
    }
}
