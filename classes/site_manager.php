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
 * Research tool manager
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research;

defined('MOODLE_INTERNAL') || die();

/**
 * Research tool site manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_manager {


    /**
     * Analyses the site courses.
     *
     * @return void
     */
    protected function analyse() {

        $studentroles = json_decode(get_config('tool_research', 'studentroles'));
        $teacherroles = json_decode(get_config('tool_research', 'teacherroles'));

        if (empty($studentroles) || empty($teacherroles)) {
            throw new moodle_exception('errornoroles', 'tool_research');
        }

        // Iterate through all potentially valid courses.
        $courses = $DB->get_recordset_select('course', 'id != :frontpage', array('frontpage' => SITEID));
        if ($courses->valid() === false) {
            $courses->close();
            return false;
        }

        $status = [];
        foreach ($courses as $coursedata) {
            $course = new course_manager($coursedata, $studentroles, $teacherroles);

            if ($course->is_valid()) {
                $course->analyse();
            }

        }
    }
}
