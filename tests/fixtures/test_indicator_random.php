<?php

class test_indicator_random extends \tool_inspire\local\indicator\binary {

    protected function calculate_sample($sampleid, $samplesorigin, $starttime, $endtime) {
        global $DB;

        return mt_rand(-1, 1);
    }
}
