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
 * Grade pass target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/lib/grade/grade_grade.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');

/**
 * Grade pass target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_pass extends base {

    protected static $coursegradeitems = array();

    public function is_linear() {
        return false;
    }

    public function get_classes() {
        return array(0, 1);
    }

    public function get_analyser_class() {
        return '\\tool_inspire\\local\\analyser\\enrolment';
    }

    public function check_analysable(\tool_inspire\analysable $analysable) {
        global $DB;

        // Not a valid target if there is no course grade item.
        self::$coursegradeitems[$analysable->get_id()] = \grade_item::fetch(array('itemtype' => 'course', 'courseid' => $analysable->get_id()));
        if (empty(self::$coursegradeitems[$analysable->get_id()])) {
            return 'There is no course grade item';
        }

        // Courses that last more than 1 year may not have a regular usage.
        if ($analysable->get_end() - $analysable->get_start() > YEARSECS) {
            return 'Duration is more than 1 year';
        }

        // Not a valid target if there are not enough course accesses.
        // Using anonymous to use the db index, not filtering by timecreated to speed it up.
        $params = array('courseid' => $analysable->get_id(), 'anonymous' => 0, 'start' => $analysable->get_start(),
            'end' => $analysable->get_end());
        list($studentssql, $studentparams) = $DB->get_in_or_equal(array_keys($analysable->get_students()), SQL_PARAMS_NAMED);
        $select = 'courseid = :courseid AND anonymous = :anonymous AND timecreated > :start AND timecreated < :end ' .
            'AND userid ' . $studentssql;
        $nlogs = $DB->count_records_select('logstore_standard_log', $select, array_merge($params, $studentparams));

        // Say 5 logs per week by half of the course students.
        $nweeks = $this->get_time_range_weeks_number($analysable->get_start(), $analysable->get_end());
        $nstudents = count($analysable->get_students());
        if ($nlogs < ($nweeks * ($nstudents / 2) * 5)) {
            return 'Not enough logs';
        }

        return true;
    }

    public function calculate_row($row, \tool_inspire\analysable $analysable, $data) {

        $params = array('userid' => $row, 'itemid' => self::$coursegradeitems[$data['course']->id]->id);
        $grade = \grade_grade::fetch($params);
        if (!$grade || !$grade->finalgrade) {
            // Not valid.
            return 0;
        }

        // TODO This should look at the course minmaxgrade setting, using the grade_grades one here.
        if ($grade->rawgrademax - $grade->rawgrademin == 0) {
            // Return the lowest one.
            return 0;
        }
        $weightedgrade = ($grade->finalgrade - $grade->rawgrademin) / ($grade->rawgrademax - $grade->rawgrademin);

        // $boundaries should contain the same number of items than self::get_classes().
        $boundaries = array(0.5, 1);
        foreach ($boundaries as $key => $classboundary) {
            if ($weightedgrade <= $classboundary) {
                $class = $key;
                break;
            }
        }

        if (!isset($class)) {
            throw new \coding_exception('Something wrong with ' . $weightedgrade . ' grade, should be between 0 and 1');
        }

        if (!in_array($class, $this->get_classes())) {
            throw new \coding_exception($class . ' class is not part of ' . json_encode($this->get_classes()));
        }

        return $class;
    }
}
