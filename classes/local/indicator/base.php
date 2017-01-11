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

    public static function get_requirements() {
        return array();
    }

    /**
     * Calculates the row.
     *
     * Return a value from self::MIN_VALUE to self::MAX_VALUE or null if the indicator can not be calculated for this row.
     *
     * @param int $row
     * @param \tool_inspire\analysable $analysable
     * @param array $data
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return float|null
     */
    abstract protected function calculate_row($row, \tool_inspire\analysable $analysable, $data, $starttime, $endtime);

    public static function get_max_value() {
        return self::MAX_VALUE;
    }

    public static function get_min_value() {
        return self::MIN_VALUE;
    }

    /**
     * Calculates the indicator.
     *
     * Returns an array of values which size matches $rows size.
     *
     * @param array $rows
     * @param \tool_inspire\analysable $analysable
     * @param array $data All required data.
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return array The format to follow is [userid] = scalar
     */
    public function calculate($rows, \tool_inspire\analysable $analysable, $data, $starttime = false, $endtime = false) {
        $calculations = [];
        foreach ($rows as $rowid => $row) {

            $calculatedvalue = $this->calculate_row($row, $analysable, $data, $starttime, $endtime);

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

            $calculations[$rowid] = $calculatedvalue;
        }

        return $calculations;
    }

    protected function get_middle_value() {
        // In the middle of self::MIN_VALUE and self::MAX_VALUE but different than 0.
        return 0.01;
    }
}
