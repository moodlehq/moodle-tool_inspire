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
 * Drop out course target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/completion/completion_completion.php');

/**
 * Drop out course target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_dropout extends binary {

    protected $coursegradeitem = null;

    public static function get_name() {
        return get_string('target:coursedropout', 'tool_inspire');
    }

    public function prediction_actions(\tool_inspire\prediction $prediction) {
        global $USER;

        $actions = parent::prediction_actions($prediction);

        $sampledata = $prediction->get_sample_data();
        $studentid = $sampledata['user']->id;

        // Send a message.
        $url = new \moodle_url('/message/index.php', array('user' => $USER->id, 'id' => $studentid));
        $pix = new \pix_icon('t/message', get_string('sendmessage', 'message'));
        $actions['studentmessage'] = new \tool_inspire\prediction_action('studentmessage', $prediction, $url, $pix, get_string('sendmessage', 'message'));

        // View outline report.
        $url = new \moodle_url('/report/outline/user.php', array('id' => $studentid, 'course' => $sampledata['course']->id,
            'mode' => 'outline'));
        $pix = new \pix_icon('i/report', get_string('outlinereport'));
        $actions['viewoutlinereport'] = new \tool_inspire\prediction_action('viewoutlinereport', $prediction, $url, $pix, get_string('outlinereport'));

        return $actions;
    }

    protected static function classes_description() {
        return array(
            get_string('labelstudentdropoutno', 'tool_inspire'),
            get_string('labelstudentdropoutyes', 'tool_inspire')
        );
    }

    /**
     * Returns the predicted classes that will be ignored.
     *
     * Overwriten because we are also interested in knowing when the student is far from the risk of dropping out.
     *
     * @return array
     */
    protected function ignored_predicted_classes() {
        return array();
    }

    public function get_analyser_class() {
        return '\\tool_inspire\\local\\analyser\\student_enrolments';
    }

    public function is_valid_analysable(\tool_inspire\analysable $course, $fortraining = true) {
        global $DB;

        if (!$course->was_started()) {
            return get_string('coursenotyetstarted', 'tool_inspire');
        }

        if (!$students = $course->get_students()) {
            return get_string('nocoursestudents', 'tool_inspire');
        }

        if (!course_format_uses_sections($course->get_course_data()->format)) {
            // We can not split activities in time ranges.
            return get_string('nocoursesections', 'tool_inspire');
        }

        if ($course->get_end() == 0) {
            // We require time end to be set.
            return get_string('nocourseendtime', 'tool_inspire');
        }

        // Ongoing courses data can not be used to train.
        if ($fortraining && !$course->is_finished()) {
            return get_string('coursenotyetfinished', 'tool_inspire');
        }

        if ($fortraining) {

            // Not a valid target for training if there are not enough course accesses.
            $params = array('courseid' => $course->get_id(), 'anonymous' => 0, 'start' => $course->get_start(),
                'end' => $course->get_end());
            list($studentssql, $studentparams) = $DB->get_in_or_equal($students, SQL_PARAMS_NAMED);
            $select = 'courseid = :courseid AND anonymous = :anonymous AND timecreated > :start AND timecreated < :end ' .
                'AND userid ' . $studentssql;
            // Using anonymous to use the db index, not filtering by timecreated to speed it up.
            $nlogs = $DB->count_records_select('logstore_standard_log', $select, array_merge($params, $studentparams));

            // At least a minimum of students activity.
            $nstudents = count($students);
            if ($nlogs / $nstudents < 10) {
                return get_string('nocourseactivity', 'tool_inspire');
            }
        }

        return true;
    }

    /**
     * calculate_sample
     *
     * The meaning of a drop out changes depending on the settings enabled in the course. Following these priorities order:
     * 1.- Course completion
     * 2.- No logs during the last quarter of the course
     *
     * @param int $sampleid
     * @param \tool_inspire\analysable $course
     * @return void
     */
    public function calculate_sample($sampleid, \tool_inspire\analysable $course) {
        global $DB;

        // TODO Even if targets are aware of the data the analyser returns, we can probably still feed samples
        // data with cached data.
        $sql = "SELECT ue.* FROM {user_enrolments} ue JOIN {user} u ON u.id = ue.userid WHERE ue.id = :ueid";
        $userenrol = $DB->get_record_sql($sql, array('ueid' => $sampleid));

        // We use completion as a success metric only when it is enabled.
        $completion = new \completion_info($course->get_course_data());
        if ($completion->is_enabled() && $completion->has_criteria()) {
            $ccompletion = new \completion_completion(array('userid' => $userenrol->userid, 'course' => $course->get_id()));
            if ($ccompletion->is_complete()) {
                return 0;
            } else {
                return 1;
            }
        }

        // No logs during the last quarter of the course.
        $courseduration = $course->get_end() - $course->get_start();
        $limit = intval($course->get_end() - ($courseduration / 4));
        $params = array('userid' => $userenrol->userid, 'courseid' => $course->get_id(), 'limit' => $limit);
        $sql = "SELECT id FROM {logstore_standard_log} WHERE courseid = :courseid AND userid = :userid AND timecreated > :limit";
        if ($DB->record_exists_sql($sql, $params)) {
            return 0;
        }
        return 1;
    }
}
