<?php

class test_indicator_fullname extends \tool_inspire\local\indicator\base {

    public static function get_requirements() {
        return ['course'];
    }

    protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable, $data, $starttime, $endtime) {
        global $DB;

        $sample = $DB->get_record('course', array('id' => $sampleid));

        $firstchar = substr($sample->fullname, 0, 1);
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
