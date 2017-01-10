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
abstract class by_course extends base {

    public function get_courses($options) {
        global $DB;

        // Default to all system courses.
        if (!empty($options['filter'])) {
            $courseids = $options['filter'];
        } else {
            // Iterate through all potentially valid courses.
            $courseids = $DB->get_fieldset_select('course', 'id', 'id != :frontpage', array('frontpage' => SITEID), 'sortorder ASC');
        }

        if (!$courseids) {
            return [];
        }

        $analysables = [];
        foreach ($courseids as $courseid) {
            $analysable = new \tool_inspire\course($courseid);
            $analysables[$analysable->get_id()] = $analysable;
        }

        return $analysables;
    }

    public function analyse($options = array()) {

        $this->options = $options;

        $status = [];
        $messages = [];
        $filesbyrangeprocessor = [];

        // This class and all children will iterate through a list of courses (\tool_inspire\course).
        $analysables = $this->get_courses($options);
        foreach ($analysables as $analysableid => $analysable) {

            list($status[$analysableid], $data) = $this->process_analysable($analysable);

            if ($status[$analysableid] === \tool_inspire\model::ANALYSE_OK) {
                // Later we will need to aggregate data by range processor.
                foreach ($data as $rangeprocessorcodename => $file) {
                    $filesbyrangeprocessor[$rangeprocessorcodename][$analysableid] = $file;
                }
            } else {
                // Store the message.
                $messages[$analysableid] = $data;
            }
        }

        // We join the datasets by range processor.
        $rangeprocessorfiles = [];
        mtrace('Merging datasets');

        foreach ($filesbyrangeprocessor as $rangeprocessorcodename => $files) {

            // Delete the previous copy.
            \tool_inspire\dataset_manager::delete_range_file($this->modelid, $rangeprocessorcodename);

            // Merge all course files into one.
            $rangeprocessorfiles[$rangeprocessorcodename] = \tool_inspire\dataset_manager::merge_datasets($files, $this->modelid,
                $rangeprocessorcodename);
        }

        return array(
            'status' => $status,
            'files' => $rangeprocessorfiles,
            'messages' => $messages
        );
    }

}
