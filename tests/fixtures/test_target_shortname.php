<?php

class test_target_shortname extends \tool_inspire\local\target\binary {

    public function get_analyser_class() {
        return '\tool_inspire\local\analyser\courses';
    }

    /**
     * High value as we want to discard random stuff and we don't want any false positives.
     * @return float
     */
    protected function min_prediction_score() {
        return 0.8;
    }

    public function is_valid_analysable(\tool_inspire\analysable $analysable) {
        // This is testing, let's make things easy.
        return true;
    }

    protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable) {
        global $DB;

        $sample = $DB->get_record('course', array('id' => $sampleid));

        if ($sample->visible == 0) {
            // We skip not-visible courses as a way to emulate the training data / prediction data difference.
            // In normal circumstances targets will return null when they receive a sample that can not be
            // processed, that same sample may be used for prediction.
            return null;
        }

        $firstchar = substr($sample->shortname, 0, 1);
        if ($firstchar === 'a') {
            return 1;
        } else if ($firstchar === 'b') {
            return 0;
        }
    }

    public function callback($sampleid, $prediction, $predictionscore) {
        return 'yeah-' . $sampleid . '-' . $prediction . '-' . $predictionscore;
    }
}
