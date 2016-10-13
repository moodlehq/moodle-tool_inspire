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
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    protected $modelid;

    protected $target;
    protected $indicators;
    protected $rangeprocessors;

    public function __construct($modelid, $target, $indicators, $rangeprocessors) {
        $this->modelid = $modelid;
        $this->target = $target;
        $this->indicators = $indicators;
        $this->rangeprocessors = $rangeprocessors;

        // Checks if the analyser satisfies the provided calculables (target + indicators) needs.
        $this->check_calculable_requirements();
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
     * @param \tool_research\analysable $analysable
     * @return array
     */
    abstract function get_rows(\tool_research\analysable $analysable);

    /**
     * Main analyser method which processes the site analysables.
     *
     * \tool_research\local\analyser\by_course and \tool_research\local\analyser\sitewide are implementing
     * this method returning site courses (by_course) and the whole system (sitewide) as analysables.
     * In most of the cases you should have enough extending from one of these classes so you don't need
     * to reimplement this method.
     *
     * @param array $filter
     * @return void
     */
    abstract function analyse($filter);

    /**
     * Checks if the analyser satisfies the calculable requirements.
     *
     * @throws requirements_exception
     * @return void
     */
    protected function check_calculable_requirements() {

        $rowsinfo = $this->rows_info();

        foreach ($this->indicators as $indicator) {
            foreach ($indicator->get_requirements() as $requirement) {
                if (!in_array($requirement, $rowsinfo)) {
                    throw new \tool_research\requirements_exception($indicator->get_codename() . ' indicator requires ' .
                        $requirement . ' which is not provided by ' . get_class($this));
                }
            }
        }
    }

    public function process_analysable($analysable) {

        $files = [];

        if (!$this->target->is_valid($analysable)) {
            return [
                [$analysable->get_id() => \tool_research\model::ANALYSABLE_STATUS_INVALID_FOR_TARGET],
                $files
            ];
        }

        foreach ($this->rangeprocessors as $rangeprocessor) {
            // Until memory usage shouldn't be specially intensive, process_analysable should
            // be where things start getting serious, memory usage at this point should remain
            // more or less stable (only new \stored_file objects) as all objects should be
            // garbage collected by php.

            if ($file = $this->process_range($rangeprocessor, $analysable)) {
                $files[$rangeprocessor->get_codename()] = $file;
            }
        }

        if (empty($files)) {
            // Flag it as invalid if the analysable wasn't valid for any range processors.
            $status = \tool_research\model::ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS;
        } else {
            $status = \tool_research\model::ANALYSABLE_STATUS_PROCESSED;
        }

        // TODO This looks confusing 1 for range processor? 1 for all? Should be 1 for analysable.
        return [
            [$analysable->get_id() => $status],
            $files
        ];
    }

    protected function process_range($rangeprocessor, $analysable) {

        if ($this->recently_analysed($rangeprocessor->get_codename(), $analysable->get_id())) {
            return false;
        }

        $rangeprocessor->set_analysable($analysable);
        if (!$rangeprocessor->is_valid_analysable()) {
            debugging('invalid analysable for this processor');
            return false;
        }

        // What is a row is defined by the analyser, it can be an enrolment, a course, a user, a question
        // attempt... it is on what we will base indicators calculations.
        $rows = $this->get_rows($analysable);
        $rangeprocessor->set_rows($rows);

        $dataset = new \tool_research\dataset_manager($this->modelid, $analysable->get_id(), $rangeprocessor->get_codename());

        // Flag the model + analysable + rangeprocessor as being analysed (prevent concurrent executions).
        $dataset->init_process();

        // Here we start the memory intensive process that will last until $data var is
        // unset (until the method is finished basically).
        $data = $rangeprocessor->calculate($rows, $target, $indicators);

        // Write all calculated data to a file.
        $file = $dataset->store($data);

        // Flag the model + analysable + rangeprocessor as analysed.
        $dataset->close_process();

        return $file;
    }

    protected function recently_analysed($rangeprocessorcodename, $analysableid) {
        $prevrun = \tool_research\dataset_manager::get_run($this->modelid, $analysableid, $rangeprocessorcodename);
        if (!$prevrun) {
            return false;
        }

        if (time() > $prevrun->timecompleted + WEEKSECS) {
            return false;
        }

        return true;
    }
}
