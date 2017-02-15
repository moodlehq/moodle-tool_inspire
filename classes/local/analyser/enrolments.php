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

    public function sample_access_context($sampleid) {
        return \context_course::instance($this->get_sample_course($sampleid));
    }

    protected function provided_samples_data() {
        return array('user_enrolments', 'course', 'user');
    }

    protected function get_all_samples(\tool_inspire\analysable $course) {
        global $DB;

        // All course enrolments.
        $instances = enrol_get_instances($course->get_id(), true);
        $enrolids = array_keys($instances);
        list($sql, $params) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
        $enrolments = $DB->get_records_select('user_enrolments', "enrolid $sql", $params);

        $samplesdata = array();
        foreach ($enrolments as $sampleid => $enrolment) {
            // TODO Confirm that this is not a in-memory object copy but just a reference to the same object.
            $samplesdata[$sampleid]['course'] = $course->get_course_data();
            $samplesdata[$sampleid]['context'] = $course->get_context();

            // TODO Use $course for this.
            $samplesdata[$sampleid]['user'] = $DB->get_record('user', array('id' => $enrolment->userid));

            // Fill the cache.
            $this->samplecourses[$sampleid] = $course->get_id();
        }

        $enrolids = array_keys($enrolments);
        return array(array_combine($enrolids, $enrolids), $samplesdata);
    }

    public function get_samples($sampleids) {
        global $DB;

        // All course enrolments.
        list($sql, $params) = $DB->get_in_or_equal($sampleids, SQL_PARAMS_NAMED);
        $enrolments = $DB->get_records_select('user_enrolments', "id $sql", $params);

        $samplesdata = array();
        foreach ($enrolments as $sampleid => $enrolment) {

            // Enrolment samples are grouped by the course they belong to, so all $sampleids belong to the same
            // course, $courseid and $coursemodinfo will only query the DB once and cache the course data in memory.
            $courseid = $this->get_sample_course($sampleid);
            $coursemodinfo = get_fast_modinfo($courseid);
            $coursecontext = \context_course::instance($courseid);

            // TODO Confirm that this is not a in-memory object copy but just a reference to the same object.
            $samplesdata[$sampleid]['course'] = $coursemodinfo->get_course();
            $samplesdata[$sampleid]['context'] = $coursecontext;

            // TODO Use $course for this.
            $samplesdata[$sampleid]['user'] = $DB->get_record('user', array('id' => $enrolment->userid));

            // Fill the cache.
            $this->samplecourses[$sampleid] = $coursemodinfo->get_course()->id;
        }

        $enrolids = array_keys($enrolments);
        return array(array_combine($enrolids, $enrolids), $samplesdata);
    }

    protected function get_sample_course($sampleid) {
        global $DB;

        if (empty($this->samplecourses[$sampleid])) {
            // TODO New function in enrollib.php
            $sql = "SELECT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                     WHERE ue.id = :userenrolmentid";

            $this->samplecourses[$sampleid] = $DB->get_field_sql($sql, array('userenrolmentid' => $sampleid));
        }

        return $this->samplecourses[$sampleid];
    }

    public function sample_description($sampleid, $contextid, $sampledata) {
        $description = fullname($sampledata['user'], true, array('context' => $contextid));
        return array($description, new \user_picture($sampledata['user']));
    }

}
