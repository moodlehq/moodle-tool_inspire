<?php

class test_indicator_min extends \tool_inspire\local\indicator\binary {
    protected function calculate_sample($sampleid, $samplesorigin, \tool_inspire\analysable $analysable, $starttime, $endtime) {
        return self::MIN_VALUE;
    }
}
