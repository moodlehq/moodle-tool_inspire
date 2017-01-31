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
 * Abstract base target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_inspire\calculable {

    /**
     * This target have linear or discrete values.
     *
     * @return bool
     */
    abstract public function is_linear();

    /**
     * Returns the analyser class that should be used along with this target.
     *
     * @return string
     */
    abstract public function get_analyser_class();

    /**
     * Allows the target to verify that the analysable is a good candidate.
     *
     * This method can be used as a quick way to discard invalid analysables.
     * e.g. Imagine that your analysable don't have students and you need them.
     *
     * @param \tool_inspire\analysable $analysable
     * @return true|string
     */
    abstract public function is_valid_analysable(\tool_inspire\analysable $analysable);

    /**
     * Calculates this target for the provided samples.
     *
     * In case there are no values to return or the provided sample is not applicable just return null.
     *
     * @param int $sample
     * @param string $tablename
     * @param \tool_inspire\analysable $analysable
     * @param array $data
     * @return float|null
     */
    abstract protected function calculate_sample($sampleid, $tablename, \tool_inspire\analysable $analysable, $data);

    /**
     * Callback to execute once a prediction has been returned from the predictions processor.
     *
     * @param int $sampleid
     * @param float|int $prediction
     * @param float $predictionscore
     * @return void
     */
    abstract public function callback($sampleid, $prediction, $predictionscore);

    /**
     * Defines a boundary to ignore predictions below the specified prediction score.
     *
     * Value should go from 0 to 1.
     *
     * @return float
     */
    protected function min_prediction_score() {
        // The default minimum discards predictions with a low score.
        return 0.6;
    }

    /**
     * Should the model callback be triggered?
     *
     * @param mixed $class
     * @return bool
     */
    public function triggers_callback($predictedclass, $predictionscore) {

        $minscore = floatval($this->min_prediction_score());
        if ($minscore < 0) {
            debugging(get_class($this) . ' minimum prediction score is below 0, please update it to a value between 0 and 1.');
        } else if ($minscore > 1) {
            debugging(get_class($this) . ' minimum prediction score is above 1, please update it to a value between 0 and 1.');
        }

        // Targets may be interested in not having a min score.
        if (!empty($minscore) && floatval($predictionscore)) {
            return false;
        }

        if (!$this->is_linear()) {
            if (in_array($predictedclass, $this->ignored_predicted_classes())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculates the target.
     *
     * Returns an array of values which size matches $samples size.
     *
     * Rows with null values will be skipped as invalid by time splitting methods.
     *
     * @param array $samples
     * @param string $tablename
     * @param \tool_inspire\analysable $analysable
     * @param array $data All required data.
     * @param integer $starttime startime is not necessary when calculating targets
     * @param integer $endtime endtime is not necessary when calculating targets
     * @return array The format to follow is [userid] = scalar|null
     */
    public function calculate($samples, $tablename, \tool_inspire\analysable $analysable, $data, $starttime = false, $endtime = false) {

        $calculations = [];
        foreach ($samples as $sampleid => $sample) {
            $calculatedvalue = $this->calculate_sample($sampleid, $tablename, $analysable, $data);

            if (!is_null($calculatedvalue)) {
                if ($this->is_linear() && ($calculatedvalue > static::get_max_value() || $calculatedvalue < static::get_min_value())) {
                    throw new \coding_exception('Calculated values should be higher than ' . static::get_min_value() .
                        ' and lower than ' . static::get_max_value() . '. ' . $calculatedvalue . ' received');
                } else if (!$this->is_linear() && static::is_a_class($calculatedvalue) === false) {
                    throw new \coding_exception('Calculated values should be one of the target classes (' .
                        json_encode(static::get_classes()) . '). ' . $calculatedvalue . ' received');
                }
            }
            $calculations[$sampleid] = $calculatedvalue;
        }
        return $calculations;
    }
}
