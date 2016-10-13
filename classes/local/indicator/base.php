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
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base indicator.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements \tool_research\calculable {


    abstract protected function get_requirements();

    /**
     * Calculates the row.
     *
     * In case there are no values to return or the indicator is not applicable just return an array of nulls.
     *
     * @param stdClass $row
     * @param array $data
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return float
     */
    abstract protected function calculate_row($row, $data, $starttime, $endtime);
}
