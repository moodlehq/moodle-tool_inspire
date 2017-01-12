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
        $this->options = $options;

        // Checks if the analyser satisfies the indicators requirements.
        $this->check_indicators_requirements();
    }

    /**
     * This function is used to check calculables needs against the info provided in the analyser rows.
     *
     * @return string[]
     */
    abstract function rows_info();

    /**
     * This function returns the list of rows that will be calculated.
     *
     * @param \tool_inspire\analysable $analysable
     * @return array
     */
    abstract function get_rows(\tool_inspire\analysable $analysable);

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
    abstract function analyse();

    /**
     * Checks if the analyser satisfies all the model indicators requirements.
     *
     * @throws requirements_exception
     * @return void
     */
    protected function check_indicators_requirements() {

        $rowsinfo = $this->rows_info();

        foreach ($this->indicators as $indicator) {
            foreach ($indicator::get_requirements() as $requirement) {
                if (empty($rowsinfo[$requirement])) {
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
     * @return array Analysable general status code AND (files by range processor OR error code)
     */
    public function process_analysable($analysable) {

        // Default returns.
        $files = array();
        $message = null;

        // Discard invalid analysables.
        $result = $this->target->is_analysable($analysable);
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
            $result = $this->process_range($rangeprocessor, $analysable);
            if (!empty($result->file)) {
                $files[$rangeprocessor->get_codename()] = $result->file;
            }
            $results[] = $result;
        }

        // Set the staus code.
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

    protected function process_range($rangeprocessor, $analysable) {

        mtrace($rangeprocessor->get_codename() . ' analysing analysable with id ' . $analysable->get_id());

        $result = new \stdClass();

        $rangeprocessor->set_analysable($analysable);
        if (!$rangeprocessor->is_valid_analysable()) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'Invalid analysable for this processor';
            return $result;
        }

        // What is a row is defined by the analyser, it can be an enrolment, a course, a user, a question
        // attempt... it is on what we will base indicators calculations.
        $rows = $this->get_rows($analysable);

        // We skip all rows that have already been used.
        $rows = $this->filter_out_analysed_rows($rows, $analysable, $rangeprocessor);

        if (count($rows) === 0) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'No new data available';
            return $result;
        }

        $rangeprocessor->set_rows($rows);

        $dataset = new \tool_inspire\dataset_manager($this->modelid, $analysable->get_id(), $rangeprocessor->get_codename(), $this->options['evaluation']);

        // Flag the model + analysable + rangeprocessor as being analysed (prevent concurrent executions).
        $dataset->init_process();

        // Here we start the memory intensive process that will last until $data var is
        // unset (until the method is finished basically).
        $data = $rangeprocessor->calculate($this->target, $this->indicators);

        if (!$data) {
            $result->status = \tool_inspire\model::ANALYSE_REJECTED_RANGE_PROCESSOR;
            $result->message = 'No valid data available';
            return $result;
        }

        // Write all calculated data to a file.
        $file = $dataset->store($data);

        // Flag the model + analysable + rangeprocessor as analysed.
        $dataset->close_process();

        // Save the rows that have been already analysed so they are not analysed again in future.
        if ($this->options['evaluation'] === false) {
            $this->save_analysed_rows($rows, $analysable, $rangeprocessor, $file);
        }

        $result->status = \tool_inspire\model::ANALYSE_OK;
        $result->message = 'Successfully analysed';
        $result->file = $file;
        return $result;
    }

    protected function filter_out_analysed_rows($rows, $analysable, $rangeprocessor) {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $analysable->get_id(),
            'rangeprocessor' => $rangeprocessor->get_codename());
        $analysedrows = $DB->get_records('tool_inspire_file_rows', $params);

        // Skip each file trained rows.
        foreach ($analysedrows as $analysedfile) {

            $alreadyanalysedrows = json_decode($analysedfile->rowids, true);

            if (!empty($alreadyanalysedrows)) {
                // Reset $rows to $rows - this field $alreadyanalysedrows.
                $rows = array_diff_key($rows, $alreadyanalysedrows);
            }
        }

        return $rows;
    }

    protected function save_analysed_rows($rows, $analysable, $rangeprocessor, $file) {
        global $DB;

        $filerows = new \stdClass();
        $filerows->modelid = $this->modelid;
        $filerows->analysableid = $analysable->get_id();
        $filerows->rangeprocessor = $rangeprocessor->get_codename();
        $filerows->fileid = $file->get_id();

        // TODO We just need the keys, we can save some space by removing the values.
        $filerows->rowids = json_encode($rows);
        $filerows->timeanalysed = time();

        return $DB->insert_record('tool_inspire_file_rows', $filerows);
    }
}
