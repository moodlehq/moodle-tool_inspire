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
    abstract public function is_analysable(\tool_inspire\analysable $analysable);

    /**
     * Calculates this target for the provided row.
     *
     * In case there are no values to return or the provided row is not applicable just return null.
     *
     * @param int $row
     * @param \tool_inspire\analysable $analysable
     * @param array $data
     * @return float|null
     */
    abstract protected function calculate_row($row, \tool_inspire\analysable $analysable, $data);

    /**
     * Returns the target discrete values.
     *
     * Only useful for targets using discrete values, must be overwriten if it is the case.
     *
     * @return array
     */
    protected function get_classes() {
        // Coding exception as this will only be called if this target have non-linear values.
        throw new \coding_exception('Overwrite get_classes() and return an array with the different target classes');
    }

    /**
     * Gets the maximum value for this target
     *
     * @return float
     */
    protected static function get_max_value() {
        // Coding exception as this will only be called if this target have linear values.
        throw new \coding_exception('Overwrite get_max_value() and return the target max value');
    }

    /**
     * Gets the minimum value for this target
     *
     * @return float
     */
    protected static function get_min_value() {
        // Coding exception as this will only be called if this target have linear values.
        throw new \coding_exception('Overwrite get_min_value() and return the target min value');
    }

    protected function is_a_class($class) {
        return (in_array($class, $this->get_classes()));
    }

    /**
     * Calculates the target.
     *
     * Returns an array of values which size matches $rows size.
     *
     * Rows with null values will be skipped as invalid by range processors.
     *
     * @param array $rows
     * @param \tool_inspire\analysable $analysable
     * @param array $data All required data.
     * @param integer $notused1 startime is not necessary when calculating targets
     * @param integer $notused2 endtime is not necessary when calculating targets
     * @return array The format to follow is [userid] = scalar|null
     */
    public function calculate($rows, \tool_inspire\analysable $analysable, $data, $notused1 = false, $notused2 = false) {

        $calculations = [];
        foreach ($rows as $rowid => $row) {
            $calculatedvalue = $this->calculate_row($row, $analysable, $data);

            if (!is_null($calculatedvalue)) {
                if ($this->is_linear() && ($calculatedvalue > self::get_max_value() || $calculatedvalue < self::get_min_value())) {
                    throw new \coding_exception('Calculated values should be higher than ' . self::get_min_value() .
                        ' and lower than ' . self::get_max_value() . '. ' . $calculatedvalue . ' received');
                } else if (!$this->is_linear() && $this->is_a_class($calculatedvalue) === false) {
                    throw new \coding_exception('Calculated values should be one of the target classes (' .
                        json_encode($this->get_classes()) . '). ' . $calculatedvalue . ' received');
                }
            }
            $calculations[$rowid] = $calculatedvalue;
        }
        return $calculations;
    }
}
