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
    protected $timesplittings;

    protected $options;

    protected $log;

    public function __construct($modelid, \tool_inspire\local\target\base $target, $indicators, $timesplittings, $options) {
        $this->modelid = $modelid;
        $this->target = $target;
        $this->indicators = $indicators;
        $this->timesplittings = $timesplittings;

        if (empty($options['evaluation'])) {
            $options['evaluation'] = false;
        }
        $this->options = $options;

        // Checks if the analyser satisfies the indicators requirements.
        $this->check_indicators_requirements();

        $this->log = array();
    }

    /**
     * This function returns the list of samples that can be calculated.
     *
     * @param \tool_inspire\analysable $analysable
     * @return array array[0] = int[], array[1] = array
     */
    abstract protected function get_all_samples(\tool_inspire\analysable $analysable);

    abstract public function get_samples($sampleids);

    abstract protected function get_samples_origin();

    /**
     * tool/inspire:listinsights will be required at this level to access the sample predictions.
     *
     * @param int $sampleid
     * @return \context
     */
    abstract public function sample_access_context($sampleid);

    abstract public function sample_description($sampleid, $contextid, $sampledata);

    protected function provided_sample_data() {
        return array($this->get_samples_origin());
    }

    /**
     * Main analyser method which processes the site analysables.
     *
     * \tool_inspire\local\analyser\by_course and \tool_inspire\local\analyser\sitewide are implementing
     * this method returning site courses (by_course) and the whole system (sitewide) as analysables.
     * In most of the cases you should have enough extending from one of these classes so you don't need
     * to reimplement this method.
     *
     * @return \stored_file[]
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
     * @throws \tool_inspire\requirements_exception
     * @return void
     */
    protected function check_indicators_requirements() {

        foreach ($this->indicators as $indicator) {
            $missingrequired = $this->check_indicator_requirements($indicator);
            if ($missingrequired !== true) {
                throw new \tool_inspire\requirements_exception(get_class($indicator) . ' indicator requires ' .
                    json_encode($missingrequired) . ' sample data which is not provided by ' . get_class($this));
            }
        }
    }

    /**
     * check_indicator_requirements
     *
     * @param \tool_inspire\local\indicator\base $indicator
     * @return true|string[] True if all good, missing requirements list otherwise
     */
    public function check_indicator_requirements(\tool_inspire\local\indicator\base $indicator) {

        $providedsampledata = $this->provided_sample_data();

        $requiredsampledata = $indicator::required_sample_data();
        if (empty($requiredsampledata)) {
            // The indicator does not need any sample data.
            return true;
        }
        $missingrequired = array_diff($requiredsampledata, $providedsampledata);

        if (empty($missingrequired)) {
            return true;
        }

        return $missingrequired;
    }

    /**
     * Processes an analysable
     *
     * This method returns the general analysable status, an array of files by time splitting method and
     * an error message if there is any problem.
     *
     * @param \tool_inspire\analysable $analysable
     * @param bool $includetarget
     * @return \stored_file[] Files by time splitting method
     */
    public function process_analysable($analysable, $includetarget) {

        // Default returns.
        $files = array();
        $message = null;

        // Target instances scope is per-analysable (it can't be lower as calculations run once per
        // analysable, not time splitting method nor time range).
        $target = forward_static_call(array($this->target, 'instance'));

        // We need to check that the analysable is valid for the target even if we don't include targets
        // as we still need to discard invalid analysables for the target.
        $result = $target->is_valid_analysable($analysable, $includetarget);
        if ($result !== true) {
            $a = new \stdClass();
            $a->analysableid = $analysable->get_id();
            $a->result = $result;
            $this->log[] = get_string('analysablenotvalidfortarget', 'tool_inspire', $a);
            return array();
        }

        // Process all provided time splitting methods.
        $results = array();
        foreach ($this->timesplittings as $timesplitting) {

            // For evaluation purposes we don't need to be that strict about how updated the data is,
            // if this analyser was analysed less that 1 week ago we skip generating a new one. This
            // helps scale the evaluation process as sites with tons of courses may a lot of time to
            // complete an evaluation.
            if (!empty($this->options['evaluation']) && !empty($this->options['reuseprevanalysed'])) {

                $previousanalysis = \tool_inspire\dataset_manager::get_evaluation_analysable_file($this->modelid,
                    $analysable->get_id(), $timesplitting->get_id());
                $boundary = time() - WEEKSECS;
                if ($previousanalysis && $previousanalysis->get_timecreated() > $boundary) {
                    // Recover the previous analysed file and avoid generating a new one.

                    // Don't bother filling a result object as it is only useful when there are no files generated.
                    $files[$timesplitting->get_id()] = $previousanalysis;
                    continue;
                }
            }

            if ($includetarget) {
                $result = $this->process_time_splitting($timesplitting, $analysable, $target);
            } else {
                $result = $this->process_time_splitting($timesplitting, $analysable);
            }

            if (!empty($result->file)) {
                $files[$timesplitting->get_id()] = $result->file;
            }
            $results[] = $result;
        }

        if (empty($files)) {
            $errors = array();
            foreach ($results as $timesplittingid => $result) {
                $errors[] = $timesplittingid . ': ' . $result->message;
            }

            $a = new \stdClass();
            $a->analysableid = $analysable->get_id();
            $a->errors =  implode(', ', $errors);
            $this->log[] = get_string('analysablenotused', 'tool_inspire', $a);
        }

        return $files;
    }

    public function get_logs() {
        return $this->log;
    }

    protected function process_time_splitting($timesplitting, $analysable, $target = false) {

        $result = new \stdClass();

        if (!$timesplitting->is_valid_analysable($analysable)) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = get_string('invalidanalysablefortimesplitting', 'tool_inspire',
                $timesplitting->get_name());
            return $result;
        }
        $timesplitting->set_analysable($analysable);

        if (CLI_SCRIPT && !PHPUNIT_TEST) {
            mtrace('Analysing id "' . $analysable->get_id() . '" with "' . $timesplitting->get_name() . '" time splitting method...');
        }

        // What is a sample is defined by the analyser, it can be an enrolment, a course, a user, a question
        // attempt... it is on what we will base indicators calculations.
        list($sampleids, $samplesdata) = $this->get_all_samples($analysable);

        if (count($sampleids) === 0) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = get_string('nodata', 'tool_inspire');
            return $result;
        }

        if ($target) {
            // All ranges are used when we are calculating data for training.
            $ranges = $timesplitting->get_all_ranges();
        } else {
            // Only some ranges can be used for prediction (it depends on the time range where we are right now).
            $ranges = $this->get_prediction_ranges($timesplitting);
        }

        // There is no need to keep track of the evaluated samples and ranges as we always evaluate the whole dataset.
        if ($this->options['evaluation'] === false) {

            if (empty($ranges)) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = get_string('nonewdata', 'tool_inspire');
                return $result;
            }

            // We skip all samples that are already part of a training dataset, even if they have noe been used for training yet.
            $sampleids = $this->filter_out_train_samples($sampleids, $timesplitting);

            if (count($sampleids) === 0) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = get_string('nonewdata', 'tool_inspire');
                return $result;
            }

            // TODO We may be interested in limiting $samplesdata contents to $sampleids after filtering out some sampleids.

            // Only when processing data for predictions.
            if ($target === false) {
                // We also filter out ranges that have already been used for predictions.
                $ranges = $this->filter_out_prediction_ranges($ranges, $timesplitting);
            }

            if (count($ranges) === 0) {
                $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
                $result->message = get_string('nonewtimeranges', 'tool_inspire');
                return $result;
            }
        }

        $dataset = new \tool_inspire\dataset_manager($this->modelid, $analysable->get_id(), $timesplitting->get_id(),
            $this->options['evaluation'], !empty($target));

        // Flag the model + analysable + timesplitting as being analysed (prevent concurrent executions).
        $dataset->init_process();

        foreach ($this->indicators as $key => $indicator) {
            // The analyser attaches the main entities the sample depends on and are provided to the
            // indicator to calculate the sample.
            $this->indicators[$key]->set_sample_data($samplesdata);
        }

        // Here we start the memory intensive process that will last until $data var is
        // unset (until the method is finished basically).
        $data = $timesplitting->calculate($sampleids, $this->get_samples_origin(), $this->indicators, $ranges, $target);

        if (!$data) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = get_string('novaliddata', 'tool_inspire');
            return $result;
        }

        // Write all calculated data to a file.
        $file = $dataset->store($data);

        // Flag the model + analysable + timesplitting as analysed.
        $dataset->close_process();

        // No need to keep track of analysed stuff when evaluating.
        if ($this->options['evaluation'] === false) {
            // Save the samples that have been already analysed so they are not analysed again in future.

            if ($target) {
                $this->save_train_samples($sampleids, $timesplitting, $file);
            } else {
                $this->save_prediction_ranges($ranges, $timesplitting);
            }
        }

        $result->status = \tool_inspire\model::OK;
        $result->message = get_string('successfullyanalysed', 'tool_inspire');
        $result->file = $file;
        return $result;
    }

    protected function get_prediction_ranges($timesplitting) {

        $now = time();

        // We already provided the analysable to the time splitting method, there is no need to feed it back.
        $predictionranges = array();
        foreach ($timesplitting->get_all_ranges() as $rangeindex => $range) {
            if ($timesplitting->ready_to_predict($range)) {
                // We need to maintain the same indexes.
                $predictionranges[$rangeindex] = $range;
            }
        }

        return $predictionranges;
    }

    protected function filter_out_train_samples($sampleids, $timesplitting) {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $timesplitting->get_analysable()->get_id(),
            'timesplitting' => $timesplitting->get_id());

        $trainingsamples = $DB->get_records('tool_inspire_train_samples', $params);

        // Skip each file trained samples.
        foreach ($trainingsamples as $trainingfile) {

            $usedsamples = json_decode($trainingfile->sampleids, true);

            if (!empty($usedsamples)) {
                // Reset $sampleids to $sampleids minus this file's $usedsamples.
                $sampleids = array_diff_key($sampleids, $usedsamples);
            }
        }

        return $sampleids;
    }

    protected function filter_out_prediction_ranges($ranges, $timesplitting) {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $timesplitting->get_analysable()->get_id(),
            'timesplitting' => $timesplitting->get_id());

        $predictedranges = $DB->get_records('tool_inspire_predict_ranges', $params);
        foreach ($predictedranges as $predictedrange) {
            if (!empty($ranges[$predictedrange->rangeindex])) {
                unset($ranges[$predictedrange->rangeindex]);
            }
        }

        return $ranges;

    }

    protected function save_train_samples($sampleids, $timesplitting, $file) {
        global $DB;

        $trainingsamples = new \stdClass();
        $trainingsamples->modelid = $this->modelid;
        $trainingsamples->analysableid = $timesplitting->get_analysable()->get_id();
        $trainingsamples->timesplitting = $timesplitting->get_id();
        $trainingsamples->fileid = $file->get_id();

        // TODO We just need the keys, we can save some space by removing the values.
        $trainingsamples->sampleids = json_encode($sampleids);
        $trainingsamples->timecreated = time();

        return $DB->insert_record('tool_inspire_train_samples', $trainingsamples);
    }

    protected function save_prediction_ranges($ranges, $timesplitting) {
        global $DB;

        $predictionrange = new \stdClass();
        $predictionrange->modelid = $this->modelid;
        $predictionrange->analysableid = $timesplitting->get_analysable()->get_id();
        $predictionrange->timesplitting = $timesplitting->get_id();
        $predictionrange->timecreated = time();

        foreach ($ranges as $rangeindex => $unused) {
            $predictionrange->rangeindex = $rangeindex;
            $DB->insert_record('tool_inspire_predict_ranges', $predictionrange);
        }
    }
}
