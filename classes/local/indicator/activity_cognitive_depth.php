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
     * This should ideally be reused by cognitive depth and social breath.
     *
     * @var \stdClass[]
     */
    protected $activitylogs = null;

    /**
     * TODO Automate this when merging into core.
     * @var string The activity name (e.g. assign or quiz)
     */
    abstract protected function get_activity_type();

    public static function required_sample_data() {
        // Only course because the indicator is valid even without users.
        return array('course');
    }

    protected function activities_level_1($sampleid, $tablename, $starttime, $endtime) {

        // May not be available.
        $user = $this->retrieve('user', $sampleid);

        if ($this->course === null) {
            // The indicator scope is a range, so all activities belong to the same course.
            $this->course = new \tool_inspire\course($this->retrieve('course', $sampleid));
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

        if ($cm = $this->retrieve('cm', $sampleid)) {
            // Samples are at cm level or below.
            $useractivities = array(\context_module::instance($cm->id)->id => $cm);
        } else {
            // All course activities.
            $useractivities = $this->course->get_activities($this->get_activity_type(), $starttime, $endtime, $user);
        }

        // Null if no activities.
        if (empty($useractivities)) {
            return null;
        }

        // We calculate the level 1 score by checking the percent of activities that the user accessed.
        $level1score = self::get_min_value();
        $scoreperactivity = (self::get_max_value() - self::get_min_value()) / count($useractivities);

        // Iterate through the module activities/resources which due date is part of this time range.
        foreach ($useractivities as $contextid => $cm) {
            // Cognitive depth level 1 is just accessing the activity.

            if (!empty($this->activitylogs[$contextid])) {
                // Someone interacted with the activity if there is no user or the user interacted with the
                // activity if there is a user.
                if (empty($user) ||
                        (!empty($user) && !empty($this->activitylogs[$contextid][$user->id]))) {
                    $level1score += $scoreperactivity;
                }
            }
        }

        return $level1score;
    }

    protected function fetch_activity_logs($activities, $starttime = false, $endtime = false) {
        global $DB;

        $select = '';
        $params = array();

        // TODO Potential huge memory demanding, think about removing text fields and force activities that
        // needs them to fetch the extra info separately.

        // Filter by context to use the db table index.
        list($contextsql, $contextparams) = $DB->get_in_or_equal(array_keys($activities), SQL_PARAMS_NAMED);

        // TODO This is an expensive query, we can set a massive distinct with all different contexts, users and actions we can find
        // in order to reduce the array size.
        $select .= "contextid $contextsql AND timecreated > :starttime AND timecreated <= :endtime";
        $params = $contextparams + array('starttime' => $starttime, 'endtime' => $endtime);
        $logs = $DB->get_records_select('logstore_standard_log', $select, $params);

        // Returs the logs organised by contextid and user so it is easier to calculate activities data later.
        $logsbycontextuser = array();
        foreach ($logs as $log) {
            if (empty($logsbycontextuser[$log->contextid])) {
                $logsbycontextuser[$log->contextid] = array();
            }
            if (empty($logsbycontextuser[$log->contextid][$log->userid])) {
                $logsbycontextuser[$log->contextid][$log->userid] = array();
            }
            $logsbycontextuser[$log->contextid][$log->userid] = $log;
        }

        return $logsbycontextuser;
    }

}
