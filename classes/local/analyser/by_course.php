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
abstract class by_course extends base {

    public function get_courses($filter) {
        global $DB;

        // Default to all system courses.
        if (empty($filter['courseids'])) {
            // Iterate through all potentially valid courses.
            $courseids = $DB->get_fieldset_select('course', 'id', 'id != :frontpage', array('frontpage' => SITEID), 'sortorder ASC');
        } else {
            $courseids = $filter['courseids'];
        }

        if (!$courseids) {
            return [];
        }

        $analysables = [];
        foreach ($courseids as $courseid) {
            $analysable = new \tool_research\course($courseid);
            $analysables[$analysable->get_id()] = $analysable;
        }

        return $analysables;
    }

    public function analyse($filter) {

        $status = [];
        $filesbyrangeprocessor = [];

        // This class and all children will iterate through a list of courses (\tool_research\course).
        $analysables = $this->get_courses($filter);
        foreach ($analysables as $analysableid => $analysable) {

            list($status[$analysableid], $analysablefiles) = $this->process_analysable($analysable);

            // Later we will need to aggregate data by range processor.
            foreach ($analysablefiles as $rangeprocessorcodename => $file) {
                $filesbyrangeprocessor[$rangeprocessorcodename][$analysableid] = $file;
            }
        }

        // We join the datasets by range processor.
        $rangeprocessorfiles = [];
        foreach ($filesbyrangeprocessor as $rangeprocessorcodename => $files) {
            $rangeprocessorfiles[$rangeprocessorcodename] = $this->merge_datasets($files);
        }

        return array($status, $rangeprocessorfiles);
    }

    protected function merge_datasets($filerecords) {
        debugging('merge datasets todo');
    }

}
