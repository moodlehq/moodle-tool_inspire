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

require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolments extends by_course {

    /**
     * @var array Cache for user_enrolment id - course id relation.
     */
    protected $samplecourses = array();

    protected function get_samples_origin() {
        return 'user_enrolments';
    }

    public function get_sample_context($sampleid) {
        return \context_course::instance($this->get_sample_course($sampleid));
    }

    protected function get_sample_course($sampleid) {
        global $DB;

        if (empty($this->samplecourses[$sampleid])) {
            $sql = "SELECT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                     WHERE ue.id = :userenrolmentid";

            $this->samplecourses[$sampleid] = $DB->get_field_sql($sql, array('userenrolmentid' => $sampleid));
        }

        return $this->samplecourses[$sampleid];
    }

    protected function provided_samples_data() {
        return array('user_enrolments', 'course', 'user');
    }

    protected function get_samples(\tool_inspire\analysable $course) {
        global $DB;

        // All course enrolments.
        $instances = enrol_get_instances($course->get_id(), true);
        $enrolids = array_keys($instances);
        list($sql, $params) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
        $enrolments = $DB->get_records_select('user_enrolments', "enrolid $sql", $params);
        $students = $course->get_students();

        $samplesdata = array();
        foreach ($enrolments as $sampleid => $enrolment) {
            $samplesdata['course'][$sampleid] = $course->get_course_data();

            // TODO Use $course for this.
            $samplesdata['user'][$sampleid] = $DB->get_record('user', array('id' => $enrolment->userid));

            // Fill the cache.
            $this->samplecourses[$sampleid] = $course->get_id();
        }

        $enrolids = array_keys($enrolments);
        return array(array_combine($enrolids, $enrolids), $samplesdata);
    }
}
