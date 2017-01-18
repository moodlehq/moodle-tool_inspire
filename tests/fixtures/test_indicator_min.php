<?php

class test_indicator_min extends \tool_inspire\local\indicator\base {
    protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable, $data, $starttime, $endtime) {
        return self::MIN_VALUE;
    }
}
