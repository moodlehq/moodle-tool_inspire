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
 * Unit tests for course manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for course manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_research_testcase extends advanced_testcase {

    public function setUp() {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $this->stu1 = $this->getDataGenerator()->create_user();
        $this->stu2 = $this->getDataGenerator()->create_user();
        $this->both = $this->getDataGenerator()->create_user();
        $this->editingteacher = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();

        $this->studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $this->editingteacherroleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        $this->teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'));


        $this->getDataGenerator()->enrol_user($this->stu1->id, $this->course->id, $this->studentroleid);
        $this->getDataGenerator()->enrol_user($this->stu2->id, $this->course->id, $this->studentroleid);
        $this->getDataGenerator()->enrol_user($this->both->id, $this->course->id, $this->studentroleid);
        $this->getDataGenerator()->enrol_user($this->both->id, $this->course->id, $this->editingteacherroleid);
        $this->getDataGenerator()->enrol_user($this->editingteacher->id, $this->course->id, $this->editingteacherroleid);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherroleid);


        set_config('studentroles', $this->studentroleid, 'tool_research');
        set_config('teacherroles', $this->editingteacherroleid . ',' . $this->teacherroleid, 'tool_research');
    }

    /**
     * Users tests.
     */
    public function test_users() {
        global $DB;

        $this->resetAfterTest(true);

        $coursemanager = new \tool_research\course_manager($this->course);
        $this->assertCount(3, $coursemanager->get_user_ids(array($this->studentroleid)));
        $this->assertCount(2, $coursemanager->get_user_ids(array($this->editingteacherroleid)));
        $this->assertCount(1, $coursemanager->get_user_ids(array($this->teacherroleid)));

        // Distinct is applied
        $this->assertCount(3, $coursemanager->get_user_ids(array($this->editingteacherroleid, $this->teacherroleid)));
        $this->assertCount(4, $coursemanager->get_user_ids(array($this->editingteacherroleid, $this->studentroleid)));
    }

    /**
     * Course validation tests.
     *
     * @return void
     */
    public function test_course_validation() {
        global $DB;

        $this->resetAfterTest(true);

        $courseman = new \tool_research\course_manager($this->course);
        $this->assertFalse($courseman->has_enough_students());
        $this->assertFalse($courseman->was_started());
        $this->assertFalse($courseman->is_finished());
        $this->assertFalse($courseman->is_valid());

        // Nothing should change when assigning as teacher.
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $this->teacherroleid);
        }
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertFalse($courseman->has_enough_students());
        $this->assertFalse($courseman->is_valid());

        // More students now.
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $this->studentroleid);
        }
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertTrue($courseman->has_enough_students());
        $this->assertFalse($courseman->is_valid());

        // Valid start date unknown end date.
        $this->course->startdate = gmmktime('0', '0', '0', 10, 24, 2015);
        $DB->update_record('course', $this->course);
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertTrue($courseman->was_started());
        $this->assertFalse($courseman->is_finished());
        $this->assertFalse($courseman->is_valid());

        // Valid start and end date.
        $this->course->enddate = gmmktime('0', '0', '0', 8, 27, 2016);
        $DB->update_record('course', $this->course);
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertTrue($courseman->was_started());
        $this->assertTrue($courseman->is_finished());
        $this->assertTrue($courseman->is_valid());

        // Valid start and ongoing course.
        $this->course->enddate = gmmktime('0', '0', '0', 8, 27, 2286);
        $DB->update_record('course', $this->course);
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertTrue($courseman->was_started());
        $this->assertFalse($courseman->is_finished());
        $this->assertFalse($courseman->is_valid());
    }

    /**
     * Course dates tests.
     *
     * @return void
     */
    public function test_start_and_end_times() {
        global $DB;

        $this->resetAfterTest(true);

        // Unknown.
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertEquals(0, $courseman->get_start());
        $this->assertEquals(0, $courseman->get_end());

        // Guess the start date based on the first student course log.
        $time = gmmktime('0', '0', '0', 10, 24, 2015);
        $this->generate_log($time);
        $courseman = new \tool_research\course_manager($this->course);

        // It should match the first log.
        $this->assertEquals($time, $courseman->get_start());

        // Having only 1 log will use that log timecreated to decide what was the course end time.

        // The end time calculation depends on the current time, so we can not check against the exact
        // time, we check against a time range instead.
        $this->assertGreaterThan($this->time_greater_than($time), $courseman->get_end());
        $this->assertLessThan($this->time_less_than($time), $courseman->get_end());

        // A course where start date was set.
        $this->course->startdate = $time;
        $DB->update_record('course', $this->course);
        $courseman = new \tool_research\course_manager($this->course);
        $this->assertEquals($this->course->startdate, $courseman->get_start());
        $this->assertGreaterThan($this->time_greater_than($time), $courseman->get_end());
        $this->assertLessThan($this->time_less_than($time), $courseman->get_end());

        // Test the ongoing course detection.
        // get_end_date looks for the 25% of different user accesses in the last month over the course
        // total of users, so we add 1 log the during the last month (total = 3 students).
        $DB->delete_records('logstore_standard_log');
        $this->generate_log($time + WEEKSECS);
        $this->generate_log($time + WEEKSECS);
        $this->generate_log($time + WEEKSECS);
        $this->generate_log(time() - WEEKSECS);

        $courseman = new \tool_research\course_manager($this->course);
        $this->assertEquals(9999999999, $courseman->get_end());


        // Explanation about get_end logic and how are we testing it:
        // - get_end_date calculates the approximate course end time using the course start time
        //   and the current time by searching for a time that contains the 95% of the student
        //   logs (\tool_research\course_manager::MIN_STUDENT_LOGS_PERCENT) so we need to add at
        //   least 20 logs so get_end can work as expected.
        // - we will try different combinations and we will check the returned course end time
        //   against a time range of 2 weeks.

        // Finished around 2 months after the start of the course (24 Oct 2015).
        $DB->delete_records('logstore_standard_log');
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 8; $j++) {
                $this->generate_log($time + (WEEKSECS * $j) + rand(10000, 99999));
            }
        }
        $courseman = new \tool_research\course_manager($this->course);
        $approximateend = $time + (WEEKSECS * 8) + 50000;
        $endtime = $courseman->get_end();
        $this->assertGreaterThan($this->time_greater_than($approximateend), $endtime);
        $this->assertLessThan($this->time_less_than($approximateend), $endtime);

        // Now we add a big bunch of logs around 6 months ago (4 weeks * 6 months), this will
        // affect the 95% calculation and the calculated course end time will be moved to 6 months ago.
        $monthsago = time() - (WEEKSECS * 4 * 6);
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j <= 4; $j++) {
                $this->generate_log($monthsago - (WEEKSECS * $j) + rand(10000, 99999));
            }
        }
        $courseman = new \tool_research\course_manager($this->course);
        $endtime = $courseman->get_end();
        $this->assertGreaterThan($this->time_greater_than($monthsago), $endtime);
        $this->assertLessThan($this->time_less_than($monthsago), $endtime);
    }

    /**
     * Get the minimum time that is considered valid according to get_end logic.
     *
     * @param int $time
     * @return int
     */
    protected function time_greater_than($time) {
        return $time - (WEEKSECS * 2);
    }

    /**
     * Get the maximum time that is considered valid according to get_end logic.
     *
     * @param int $time
     * @return int
     */
    protected function time_less_than($time) {
        return $time + (WEEKSECS * 2);
    }

    /**
     * Generate a log.
     *
     * @param int $time
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    protected function generate_log($time, $userid = false, $courseid = false) {
        global $DB;

        if (empty($userid)) {
            $userid = $this->stu1->id;
        }
        if (empty($courseid)) {
            $courseid = $this->course->id;
        }

        $context = context_course::instance($courseid);
        $obj = (object)[
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'objecttable' => 'course',
            'objectid' => $courseid,
            'crud' => 'r',
            'edulevel' => \core\event\base::LEVEL_PARTICIPATING,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => $userid,
            'courseid' => $courseid,
            'relateduserid' => null,
            'anonymous' => 0,
            'other' => null,
            'timecreated' => $time,
            'origin' => 'web',
        ];
        $DB->insert_record('logstore_standard_log', $obj);
    }

}

