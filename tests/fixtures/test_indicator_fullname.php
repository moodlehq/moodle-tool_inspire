<?php

class test_indicator_fullname extends \tool_inspire\local\indicator\linear {

    public static function required_sample() {
        return 'course';
    }

    protected static function include_averages() {
        return false;
    }

    protected function calculate_sample($sampleid, $samplesorigin, \tool_inspire\analysable $analysable, $starttime, $endtime) {
        global $DB;

        $course = $this->retrieve('course', $sampleid);

        $firstchar = substr($course->fullname, 0, 1);
        if ($firstchar === 'a') {
            return self::MIN_VALUE;
        } else if ($firstchar === 'b') {
            return -0.2;
        } else if ($firstchar === 'c') {
            return 0.2;
        } else {
            return self::MAX_VALUE;
        }
    }

}
