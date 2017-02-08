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

    public function prediction_template() {
        return 'tool_inspire/dropout_student';
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
        return '\\tool_inspire\\local\\analyser\\enrolments';
    }

    public function is_valid_analysable(\tool_inspire\analysable $course, $fortraining = true) {
        global $DB;

        if (!$course->was_started()) {
            return 'Course is not yet started';
        }

        // Courses that last more than 1 year may not have a regular usage.
        if ($course->get_end() - $course->get_start() > YEARSECS) {
            return 'Duration is more than 1 year';
        }

        if (!$students = $course->get_students()) {
            return 'No students';
        }

        // Ongoing courses data can not be used to train.
        if (!$course->is_finished() && $fortraining) {
            return 'Course is not yet finished';
        }

        // Not a valid target if there are not enough course accesses.
        // Using anonymous to use the db index, not filtering by timecreated to speed it up.
        $params = array('courseid' => $course->get_id(), 'anonymous' => 0, 'start' => $course->get_start(),
            'end' => $course->get_end());
        list($studentssql, $studentparams) = $DB->get_in_or_equal($students, SQL_PARAMS_NAMED);
        $select = 'courseid = :courseid AND anonymous = :anonymous AND timecreated > :start AND timecreated < :end ' .
            'AND userid ' . $studentssql;
        $nlogs = $DB->count_records_select('logstore_standard_log', $select, array_merge($params, $studentparams));

        // Say 5 logs per week by half of the course students.
        $nweeks = $this->get_time_range_weeks_number($course->get_start(), $course->get_end());
        $nstudents = count($course->get_students());
        if ($nlogs < ($nweeks * ($nstudents / 2) * 5)) {
            return 'Not enough logs';
        }

        // Now we check that we can analyse the course through course completion, course competencies or grades.
        $nogradeitem = false;
        $nogradevalue = false;
        $nocompletion = false;
        $nocompetencies = false;

        $completion = new \completion_info($course->get_course_data());
        if (!$completion->is_enabled() && $completion->has_criteria()) {
            $nocompletion = true;
        }

        if (\core_competency\api::is_enabled() && \core_competency\api::count_competencies_in_course($course->get_id()) === 0) {
            $nocompetencies = true;
        }

        // Not a valid target if there is no course grade item.
        $this->coursegradeitem = \grade_item::fetch(array('itemtype' => 'course', 'courseid' => $course->get_id()));
        if (empty($this->coursegradeitems)) {
            $nogradeitem = true;
        }

        if ($this->coursegradeitem->gradetype !== GRADE_TYPE_VALUE) {
            $nogradevalue = true;
        }

        if ($nocompletion === true && $nocompetencies === true && ($nogradeitem || $nogradevalue)) {
            return 'No course pass method available (no completion nor competencies or course grades';
        }

        return true;
    }

    /**
     * calculate_sample
     *
     * The meaning of a drop out changes depending on the settings enabled in the course. Following these priorities order:
     * 1.- Course completion
     * 2.- All course competencies completion
     * 3.- Course final grade over grade pass
     * 4.- Course final grade below 50% of the course maximum grade
     *
     * @param int $sampleid
     * @param \tool_inspire\analysable $course
     * @return void
     */
    public function calculate_sample($sampleid, \tool_inspire\analysable $course) {
        global $DB;

        // TODO We can probably feed samples data here as well.
        $userenrolment = $DB->get_record('user_enrolments', array('id' => $sampleid));
        $user = $DB->get_record('user', array('id' => $userenrolment->userid));

        // We use completion as a success metric only when it is enabled.
        $completion = new \completion_info($course->get_course_data());
        if ($completion->is_enabled() && $completion->has_criteria()) {
            $ccompletion = new \completion_completion(array('userid' => $user->id, 'course' => $course->get_id()));
            if ($ccompletion->is_complete()) {
                return 0;
            } else {
                return 1;
            }
        }

        // Same with competencies.
        if (\core_competency\api::is_enabled()) {
            $ncoursecompetencies = \core_competency\api::count_competencies_in_course($course->get_id());
            if ($ncoursecompetencies > 0) {
                $nusercompetencies = \core_competency\api::count_proficient_competencies_in_course_for_user(
                    $course->get_id(), $user->id);
                if ($ncoursecompetencies > $nusercompetencies) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }

        // At this stage we know that the course grade item exists.
        if (empty($this->coursegradeitem)) {
            $this->coursegradeitem = \grade_item::fetch(array('itemtype' => 'course', 'courseid' => $course->get_id()));
        }

        // Falling back to the course grades.
        $params = array('userid' => $user->id, 'itemid' => $this->coursegradeitem->id);
        $grade = \grade_grade::fetch($params);
        if (!$grade || !$grade->finalgrade) {
            // We checked that the course is suitable for being analysed in is_valid_analysable so if the
            // student do not have a final grade it is because there are no grades for that student, which is bad.
            return 1;
        }

        $passed = $grade->is_passed();
        // is_passed returns null if there is no pass grade or can't be calculated.
        if ($passed !== null) {
            // Returning the opposite as 1 means dropout user.
            return !$passed;
        }

        // Pass if gets more than 50% of the course grade.
        $mingrade = $grade->get_grade_min();
        $maxgrade = $grade->get_grade_max();
        $weightedgrade = ($grade->finalgrade - $mingrade) / ($maxgrade - $mingrade);

        if ($weightedgrade >= 0.5) {
            return 0;
        }

        return 1;
    }
}
