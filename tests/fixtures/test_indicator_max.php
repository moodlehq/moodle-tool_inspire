<?php

class test_indicator_max extends \tool_inspire\local\indicator\base {
    protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable, $data, $starttime, $endtime) {
        return self::MAX_VALUE;
    }
}
