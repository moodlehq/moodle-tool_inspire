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
 * Abstract base indicator.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base indicator.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_inspire\calculable {

    const MIN_VALUE = -1;

    const MAX_VALUE = 1;

    /**
     * @var array[]
     */
    protected $sampledata = array();

    /**
     * Converts the calculated indicators to feature/s.
     *
     * @param float|int[] $calculatedvalues
     * @return array
     */
    abstract protected function to_features($calculatedvalues);

    /**
     * Calculates the sample.
     *
     * Return a value from self::MIN_VALUE to self::MAX_VALUE or null if the indicator can not be calculated for this sample.
     *
     * @param int $sampleid
     * @param string $sampleorigin
     * @param \tool_inspire\analysable $analysable
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return float|null
     */
    abstract protected function calculate_sample($sampleid, $sampleorigin, \tool_inspire\analysable $analysable, $starttime, $endtime);

    /**
     * @return null|string
     */
    public static function required_sample() {
        return null;
    }

    public static function instance() {
        return new static();
    }

    public static function get_max_value() {
        return self::MAX_VALUE;
    }

    public static function get_min_value() {
        return self::MIN_VALUE;
    }

    /**
     * Calculates the indicator.
     *
     * Returns an array of values which size matches $sampleids size.
     *
     * @param array $sampleids
     * @param string $samplesorigin
     * @param \tool_inspire\analysable $analysable
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return array The format to follow is [userid] = int|float[]
     */
    public function calculate($sampleids, $samplesorigin, \tool_inspire\analysable $analysable, $starttime = false, $endtime = false) {

        $calculations = array();
        foreach ($sampleids as $sampleid => $unusedsampleid) {

            $calculatedvalue = $this->calculate_sample($sampleid, $samplesorigin, $analysable, $starttime, $endtime);

            if (is_null($calculatedvalue)) {
                // Converted to 0 = unknown.
                $calculatedvalue = 0;
            } else if ($calculatedvalue === 0) {
                // We convert zeros to the minimal non-zero value.
                $calculatedvalue = $this->get_middle_value();
            } else if ($calculatedvalue > self::MAX_VALUE || $calculatedvalue < self::MIN_VALUE) {
                throw new \coding_exception('Calculated values should be higher than ' . self::MIN_VALUE .
                    ' and lower than ' . self::MAX_VALUE . ' ' . $calculatedvalue . ' received');
            }

            $calculations[$sampleid] = $calculatedvalue;
        }

        $calculations = $this->to_features($calculations);

        return $calculations;
    }

    public function set_sample_data($data) {
        $this->sampledata = $data;
    }

    protected function retrieve($tablename, $sampleid) {
        if (empty($this->sampledata[$tablename]) || empty($this->sampledata[$tablename][$sampleid])) {
            // We don't throw an exception because indicators should be able to
            // try multiple tables until they find something they can use.
            return false;
        }
        return $this->sampledata[$tablename][$sampleid];
    }

    protected function get_middle_value() {
        // In the middle of self::MIN_VALUE and self::MAX_VALUE but different than 0.
        return 0.01;
    }

    protected static function add_samples_averages() {
        return false;
    }

    protected static function get_average_columns() {
        return array('mean');
    }

    protected function calculate_averages($values) {
        $mean = array_sum($values) / count($values);
        return array($mean);
    }
}
