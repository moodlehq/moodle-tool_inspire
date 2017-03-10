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
 * Cognitive depth abstract indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Cognitive depth abstract indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class activity_cognitive_depth extends linear {

    protected $course = null;
    /**
     * TODO This should ideally be reused by cognitive depth and social breath.
     *
     * @var array Array of logs by [contextid][userid]
     */
    protected $activitylogs = null;

    /**
     * @var array Array of grades by [contextid][userid]
     */
    protected $grades = null;

    /**
     * TODO Automate this when merging into core.
     * @var string The activity name (e.g. assign or quiz)
     */
    abstract protected function get_activity_type();

    public static function required_sample_data() {
        // Only course because the indicator is valid even without students.
        return array('course');
    }

    protected function activities_level_1($sampleid, $tablename, $starttime, $endtime) {

        // May not be available.
        $user = $this->retrieve('user', $sampleid);

        if (!$useractivities = $this->get_student_activities($sampleid, $tablename, $starttime, $endtime)) {
            // Null if no activities.
            return null;
        }

        // We calculate the level 1 score by checking the percent of activities that the user accessed.
        $level1score = self::get_min_value();
        $scoreperactivity = (self::get_max_value() - self::get_min_value()) / count($useractivities);

        // Iterate through the module activities/resources which due date is part of this time range.
        foreach ($useractivities as $contextid => $unused) {
            // Cognitive depth level 1 is just accessing the activity.

            // Half of the score if only level 1 interaction.
            if ($this->any_log($contextid, $user)) {
                $level1score += $scoreperactivity;
            }
        }

        return $level1score;
    }

    protected function activities_level_2($sampleid, $tablename, $starttime, $endtime) {

        // May not be available.
        $user = $this->retrieve('user', $sampleid);

        $useractivities = $this->get_student_activities($sampleid, $tablename, $starttime, $endtime);

        // Null if no activities.
        if (empty($useractivities)) {
            return null;
        }

        // We calculate the level 2 score by checking the percent of activities where the user performed
        // create or update actions.
        $level2score = self::get_min_value();
        $scoreperactivity = (self::get_max_value() - self::get_min_value()) / count($useractivities);

        // We divide the total score a user can get for this activity by the number of levels (= 2).
        $scoreperlevel = $scoreperactivity / 2;

        // Iterate through the module activities/resources which due date is part of this time range.
        foreach ($useractivities as $contextid => $unused) {
            // Cognitive depth level 2 is to submit content.

            if ($this->any_write_log($contextid, $user)) {
                $level2score += $scoreperactivity;
                // Max score for this activity.
                continue;
            }

            // Half of the score if only level 1 interaction.
            if ($this->any_log($contextid, $user)) {
                $level2score += $scoreperlevel;
            }
        }

        return $level2score;
    }

    protected function activities_level_3($sampleid, $tablename, $starttime, $endtime) {

        // May not be available.
        $user = $this->retrieve('user', $sampleid);

        $useractivities = $this->get_student_activities($sampleid, $tablename, $starttime, $endtime);

        // Null if no activities.
        if (empty($useractivities)) {
            return null;
        }

        // We calculate the level 3 score by checking the percent of activities where the user performed
        // create or update actions.
        $level3score = self::get_min_value();
        $scoreperactivity = (self::get_max_value() - self::get_min_value()) / count($useractivities);

        // We divide the total score a user can get for this activity by the number of levels (= 3).
        $scoreperlevel = $scoreperactivity / 3;

        // Iterate through the module activities/resources which due date is part of this time range.
        foreach ($useractivities as $contextid => $unused) {
            // Cognitive depth level 3 is to view feedback.

            if ($this->any_feedback_view($contextid, $user, $this->feedback_events())) {
                $level3score += $scoreperactivity;
                continue;
            }

            if ($this->any_write_log($contextid, $user)) {
                $level3score += $scoreperlevel * 2;
                continue;
            }

            // Half of the score if only level 1 interaction.
            if ($this->any_log($contextid, $user)) {
                $level3score += $scoreperlevel;
            }
        }

        return $level3score;
    }

    protected final function any_log($contextid, $user) {
        if (empty($this->activitylogs[$contextid])) {
            return false;
        }

        // Someone interacted with the activity if there is no user or the user interacted with the
        // activity if there is a user.
        if (empty($user) ||
                (!empty($user) && !empty($this->activitylogs[$contextid][$user->id]))) {
            return true;
        }

        return false;
    }

    protected final function any_write_log($contextid, $user) {
        if (empty($this->activitylogs[$contextid])) {
            return false;
        }

        // No specific user, we look at all activity logs.
        if (!$user) {
            foreach ($this->activitylogs[$contextid] as $userlogs => $logs) {
                foreach ($logs as $log) {
                    if ($log->crud === 'c' || $log->crud === 'u') {
                        return true;
                    }
                }
            }
        } else if (!empty($this->activitylogs[$contextid][$user->id])) {
            // TODO This else if looks crap, try to do it better without hurting performance.
            foreach ($this->activitylogs[$contextid][$user->id] as $log) {
                if ($log->crud === 'c' || $log->crud === 'u') {
                    return true;
                }
            }
        }

        return false;
    }

    protected final function any_feedback_view($contextid, $user, $eventnames) {
        if (empty($this->activitylogs[$contextid])) {
            return false;
        }

        if (empty($this->grades[$contextid])) {
            return false;
        }

        // No specific user, we look at all grades and activity logs.
        if (!$user) {
            foreach ($this->grades[$contextid] as $userid => $gradeitems) {
                foreach ($gradeitems as $gradeitem) {
                    if (!empty($gradeitem->feedback)) {
                        if ($this->feedback_viewed_after($contextid, $userid, $gradeitem->dategraded)) {
                            // A single user that viewed the feedback is enough if no user was specified.
                            return true;
                        }
                    }
                }
            }
        } else if (!empty($this->grades[$contextid][$user->id])) {
            foreach ($this->grades[$contextid][$user->id] as $gradeitems) {
                foreach ($gradeitems as $gradeitem) {
                    if ($this->feedback_viewed_after($contextid, $user->id, $gradeitem->dategraded)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function get_student_activities($sampleid, $tablename, $starttime, $endtime) {

        // May not be available.
        $user = $this->retrieve('user', $sampleid);

        if ($this->course === null) {
            // The indicator scope is a range, so all activities belong to the same course.
            $this->course = \tool_inspire\course::instance($this->retrieve('course', $sampleid));
        }

        if ($this->activitylogs === null) {
            // Fetch all activity logs in each activity in the course, not restricted to a specific sample so we can cache it.

            $courseactivities = $this->course->get_all_activities($this->get_activity_type());

            // Null if no activities of this type in this course.
            if (empty($courseactivities)) {
                $this->activitylogs = false;
                return null;
            }
            $this->activitylogs = $this->fetch_activity_logs($courseactivities, $starttime, $endtime);
        }

        if ($this->grades === null) {
            $courseactivities = $this->course->get_all_activities($this->get_activity_type());
            $this->grades = $this->course->get_student_grades($courseactivities);
        }

        if ($cm = $this->retrieve('cm', $sampleid)) {
            // Samples are at cm level or below.
            $useractivities = array(\context_module::instance($cm->id)->id => $cm);
        } else {
            // All course activities.
            $useractivities = $this->course->get_activities($this->get_activity_type(), $starttime, $endtime, $user);
        }

        return $useractivities;
    }

    protected function fetch_activity_logs($activities, $starttime = false, $endtime = false) {
        global $DB;

        // TODO Potential huge memory demanding, think about removing text fields and force activities that
        // needs them to fetch the extra info separately.

        // Filter by context to use the db table index.
        list($contextsql, $contextparams) = $DB->get_in_or_equal(array_keys($activities), SQL_PARAMS_NAMED);

        // TODO This is an expensive query, we can set a massive distinct with all different contexts, users and actions we can find
        // in order to reduce the array size.

        $fields = 'eventname, crud, contextid, contextlevel, contextinstanceid, userid, courseid, relateduserid';
        $select = "contextid $contextsql AND timecreated > :starttime AND timecreated <= :endtime";
        $sql = "SELECT $fields, max(timecreated) AS timecreated " .
            "FROM {logstore_standard_log} " .
            "WHERE $select " .
            "GROUP BY $fields " .
            "ORDER BY timecreated ASC";
        $params = $contextparams + array('starttime' => $starttime, 'endtime' => $endtime);
        $logs = $DB->get_recordset_sql($sql, $params);

        // Returs the logs organised by contextid and user so it is easier to calculate activities data later.
        $logsbycontextuser = array();
        foreach ($logs as $log) {
            if (!isset($logsbycontextuser[$log->contextid])) {
                $logsbycontextuser[$log->contextid] = array();
            }
            if (!isset($logsbycontextuser[$log->contextid][$log->userid])) {
                $logsbycontextuser[$log->contextid][$log->userid] = array();
            }
            $logsbycontextuser[$log->contextid][$log->userid][] = $log;
        }
        $logs->close();

        return $logsbycontextuser;
    }

}
