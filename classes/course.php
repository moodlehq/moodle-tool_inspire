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

namespace tool_research;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course implements \tool_research\analysable {

    // TODO Force people to access this through a method, we may want to change values in future
    // and allowing direct access to them restrict how we can manage backwards compatibility.
    const MAX_TIME = 9999999999;
    const MIN_NUMBER_STUDENTS = 10;
    const MIN_NUMBER_LOGS = 100;
    const MIN_STUDENT_LOGS_PERCENT = 90;

    protected static $instance = null;

    protected $studentroles = [];
    protected $teacherroles = [];

    protected $course = null;
    protected $coursecontext = null;
    protected $starttime = null;
    protected $started = null;
    protected $endtime = null;
    protected $finished = null;

    protected $students = [];
    protected $teachers = [];

    protected $ntotallogs = null;

    /**
     * Course manager constructor.
     *
     * Use self::instance() instead to get a course_manager instance as it returns cached copies.
     *
     * Loads course students and teachers.
     *
     * Let's try to keep this computationally inexpensive.
     *
     * @param $courseid Course id
     * @param array $studentroles
     * @param array $teacherroles
     * @return void
     */
    public function __construct($courseid) {

        $this->course = get_course($courseid);

        $this->coursecontext = \context_course::instance($this->course->id);

        $this->studentroles = explode(',', get_config('tool_research', 'studentroles'));

        $this->teacherroles = explode(',', get_config('tool_research', 'teacherroles'));

        $this->now = time();

        if (empty($this->studentroles) || empty($this->teacherroles)) {
            // Unexpected, site settings should be set with default values.
            throw new \moodle_exception('errornoroles', 'tool_research');
        }

        // Get the course users, including users assigned to student and teacher roles at an higher context.
        $this->students = $this->get_users($this->studentroles);
        $this->teachers = $this->get_users($this->teacherroles);
    }

    public function get_id() {
        return $this->course->id;
    }

    public function get_usual_required_records() {
        global $DB;

        $storage = [];

        $storage['course'] = $this->course;

        list($sql, $params) = $DB->get_in_or_equal(array_keys($this->students + $this->teachers));
        $storage['user'] = $DB->get_records_select('user', 'id ' . $sql, $params);

        return $storage;
    }

    public function get_metadata() {
        // TODO We should be very careful with what we include here this is a WIP example.
        return [
            'courseid' => $this->course->id,
            'courseshortname' => $this->course->shortname,
        ];
    }


    /**
     * Get the course start timestamp.
     *
     * @return int Timestamp or 0 if has not started yet.
     */
    public function get_start_time() {
        global $DB;

        if ($this->starttime !== null) {
            return $this->starttime;
        }

        // The field always exist but may have no valid if the course is created through a sync process.
        if (!empty($this->course->startdate)) {
            $this->starttime = $this->course->startdate;
        } else {
            // Fallback to the first student log.
            list($studentssql, $studentsparams) = $DB->get_in_or_equal($this->students, SQL_PARAMS_NAMED);
            $select = 'courseid = :courseid AND userid ' . $studentssql;
            $params = ['courseid' => $this->course->id] + $studentsparams;
            $records = $DB->get_records_select('logstore_standard_log', $select, $params,
                'timecreated ASC', 'id, timecreated', 0, 1);
            if (!$records) {
                // If there are no logs we assume the course has not started yet.
                $this->starttime = 0;
                return $this->starttime;
            }
            $this->starttime  = reset($records)->timecreated;
        }

        return $this->starttime;
    }

    /**
     * Get the course end timestamp.
     *
     * @return int Timestamp, self::MAX_TIME if we don't know but ongoing and 0 if we can not work it out.
     */
    public function get_end_time() {
        global $DB;

        if ($this->endtime !== null) {
            return $this->endtime;
        }

        // The enddate field is only available from Moodle 3.2 (MDL-22078).
        if (!empty($this->course->enddate)) {
            $this->endtime = $this->course->enddate;
            return $this->endtime;
        }

        // Not worth trying if we weren't able to determine the startdate, we need to base the calculations below on the
        // course start date.
        $starttime = $this->get_start_time();
        if (!$starttime) {
            $this->endtime = 0;
            return $this->endtime;
        }

        if (empty($this->students)) {
            $this->endtime = 0;
            return $this->endtime;
        }

        if ($this->get_total_logs() === 0) {
            // No way to know if there are no logs.
            $this->endtime = 0;
            return $this->endtime;
        }

        list($filterselect, $filterparams) = $this->get_query_filters();

        $sql = "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log} " .
            "WHERE $filterselect AND timecreated > :timecreated";
        $params = $filterparams + array('timecreated' => $this->now - (WEEKSECS * 4));
        $ntotallastmonth = $DB->count_records_sql($sql, $params);

        // If more than 1/4 of the students accessed the course in the last 4 weeks we can consider that
        // the course is still ongoing and we can not determine when it will finish.
        if ($ntotallastmonth > count($this->students) / 4) {
            $this->endtime = self::MAX_TIME;
            return $this->endtime;
        }

        // We consider that the course was already finished. We still need to work out a date though,
        // this may be computationally expensive.
        //
        // We will consider the end date the approximate date when
        // the {self::MIN_STUDENT_LOGS_PERCENT}% of the student logs are included.
        //
        // Default to ongoing. This may not be the best choice for courses with not much accesses, we
        // may want to update self::MIN_STUDENT_LOGS_PERCENT in those cases.
        $bestcandidate = self::MAX_TIME;

        // We also store the percents so we can evaluate the algorithm and constants used.
        $logspercents = [];

        // We continuously try to find out the final course week until we reach 'a week' accuracy.
        list($loopstart, $looptime, $loopend) = $this->update_loop_times($starttime, $this->now);

        do {

            // Add the time filters to the sql query.
            $select = $filterselect . " AND timecreated >= :starttime AND timecreated <= :endtime";
            $params = $filterparams + ['starttime' => $starttime, 'endtime' => $looptime];
            $nlogs = $DB->count_records_select('logstore_standard_log', $select, $params);

            // Move $looptime ahead or behind to find the more accurate end date according
            // to self::MIN_STUDENT_LOGS_PERCENT.
            $logspercents[$looptime] = intval($nlogs / $this->get_total_logs() * 100);
            if ($nlogs !== 0 && $logspercents[$looptime] > self::MIN_STUDENT_LOGS_PERCENT) {
                // We satisfy MIN_STUDENT_LOGS_PERCENT so we have a valid result.

                // Store this value as the best end time candidate if the $looptime is lower than the
                // previous best candidate.
                if ($looptime < $bestcandidate) {
                    $bestcandidate = $looptime;
                }

                // Go back in time to refine the end time. We want as much accuracy as possible.
                list($loopstart, $looptime, $loopend) = $this->update_loop_times($loopstart, $looptime);
            } else {
                // We move ahead in time if we don't reach the minimum percent and if $nlogs === 0 (no logs between
                // starttime and $looptime).
                list($loopstart, $looptime, $loopend) = $this->update_loop_times($looptime, $loopend);
            }

        // We continuously check until we get 'a week' accuracy.
        } while ($loopend - $loopstart > WEEKSECS);

        // We couldn't work out any date with more logs than self::MIN_STUDENT_LOGS_PERCENT, notify the admin running
        // the script about it.
        if ($bestcandidate === self::MAX_TIME) {
            debugging(json_encode($logspercents));
        }

        $this->endtime = $bestcandidate;
        return $this->endtime;
    }

    /**
     * Is the course valid to extract indicators from it?
     *
     * @return bool
     */
    public function is_valid() {

        if (!$this->was_started() || !$this->is_finished() || !$this->has_enough_students()) {
            return false;
        }

        // Ideally we would test this, but I don't want to generate self::MIN_NUMBER_LOGS for
        // each tests and more importantly would make test_start_and_end_times really hard to
        // test.
        if (!PHPUNIT_TEST) {
            if (!$this->has_enough_logs()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Has the course started?
     *
     * @return bool
     */
    public function was_started() {

        if ($this->started === null) {
            if ($this->get_start_time() === 0 || $this->now < $this->get_start_time()) {
                // Not yet started.
                $this->started = false;
            } else {
                $this->started = true;
            }
        }

        return $this->started;
    }

    /**
     * Has the course finished?
     *
     * @return bool
     */
    public function is_finished() {

        if ($this->finished === null) {
            $endtime = $this->get_end_time();
            if ($endtime === 0 || $this->now < $endtime) {
                // It is not yet finished or no idea when it finishes.
                $this->finished = false;
            } else {
                $this->finished = true;
            }
        }

        return $this->finished;
    }

    /**
     * Whether the course has enough students to analyse it or not.
     *
     * @return bool
     */
    public function has_enough_students() {
        if (count($this->students) >= self::MIN_NUMBER_STUDENTS) {
            return true;
        }
        return false;
    }

    /**
     * Whether the course has enough logs to be worth analysing it.
     *
     * @return bool
     */
    public function has_enough_logs() {
        if ($this->get_total_logs() < self::MIN_NUMBER_LOGS) {
            return false;
        }
        if ($this->get_total_logs() < count($this->students)) {
            return false;
        }

        return true;
    }

    /**
     * Returns a list of user ids matching the specified roles in this course.
     *
     * @param array $roleids
     * @return array
     */
    public function get_users($roleids) {

        // We need to index by ra.id as a user may have more than 1 $roles role.
        $records = get_role_users($roleids, $this->coursecontext, true, 'ra.id, u.id AS userid, r.id AS roleid', 'ra.id ASC');

        // If a user have more than 1 $roles role array_combine will discard the duplicate.
        $callable = array($this, 'filter_user_id');
        $userids = array_values(array_map($callable, $records));
        return array_combine($userids, $userids);
    }

    /**
     * Returns the total number of student logs in the course
     *
     * @return int
     */
    public function get_total_logs() {
        global $DB;

        if ($this->ntotallogs === null) {
            list($filterselect, $filterparams) = $this->get_query_filters();
            $this->ntotallogs = $DB->count_records_select('logstore_standard_log', $filterselect, $filterparams);
        }

        return $this->ntotallogs;
    }

    /**
     * Used by get_users to extract the user id.
     *
     * @param \stdClass $record
     * @return int The user id.
     */
    protected function filter_user_id($record) {
        return $record->userid;
    }

    /**
     * Returns the average time between 2 timestamps.
     *
     * @param int $start
     * @param int $end
     * @return array [starttime, averagetime, endtime]
     */
    protected function update_loop_times($start, $end) {
        $avg = intval(($start + $end) / 2);
        return array($start, $avg, $end);
    }

    /**
     * Returns the query and params used to filter {logstore_standard_log} table by this course students.
     *
     * @return array
     */
    protected function get_query_filters() {
        global $DB;

        // Check the amount of student logs in the 4 previous weeks.
        list($studentssql, $studentsparams) = $DB->get_in_or_equal($this->students, SQL_PARAMS_NAMED);
        $filterselect = "courseid = :courseid AND userid $studentssql";
        $filterparams = array('courseid' => $this->course->id) + $studentsparams;

        return array($filterselect, $filterparams);
    }
}