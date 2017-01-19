<?php

class test_indicator_random extends \tool_inspire\local\indicator\base {

    public static function get_requirements() {
        return ['course'];
    }

    protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable, $data, $starttime, $endtime) {
        global $DB;

        return mt_rand(-1, 1);
    }
}
