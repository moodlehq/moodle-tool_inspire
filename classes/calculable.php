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
 * Calculable dataset items abstract class.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculable dataset items abstract class.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class calculable {

    /**
     * Return database records required to perform a calculation, for all course students.
     *
     * Course data and course users data is available in $this->course, $this->students and
     * $this->teachers. In self::calculate you can also access $data['course'] and
     * $data['user'], this last one including only students and teachers by default.
     *
     * Please, only load whatever info you really need as all this data will be stored in
     * memory so only include boolean and integer fields, you can also include string fields if you know
     * that they will not contain big chunks of text.
     *
     * You can just return null if:
     * - You need hardly reusable records
     * - You can sort the calculation out easily using a single query fetching data from multiple db tables
     * - You need fields that can be potentially big (varchars and texts)
     *
     * IMPORTANT! No database writes are allowed here as we keep track of all different dataset items requirements.
     *
     * @return null|array The format to follow is [tablename][id] = stdClass(dbrecord)
     */
    abstract public function get_required_records();

    /**
     * Calculates the calculable.
     *
     * Returns an array of values which size matches $rows size.
     *
     * @param array $rows
     * @param array $data All required data.
     * @param integer $starttime Limit the calculation to this timestart
     * @param integer $endtime Limit the calculation to this timeend
     * @return array The format to follow is [userid] = scalar
     */
    public function calculate($rows, $data, $starttime = false, $endtime = false) {
        $calculations = [];
        foreach ($rows as $rowid => $row) {
            // TODO Comment about different child interfaces or solve this somehow.
            $calculations[$rowid] = $this->calculate_row($row, $data, $starttime, $endtime);
        }
        return $calculations;
    }

    /**
     * Default.
     *
     * Feel free to overwrite but return PARAM_ALPHANUMEXT.
     *
     * @return string
     */
    public function get_name() {
        return get_class($this);
    }

    /**
     * Returns a code name for the indicator.
     *
     * Used as column identificator.
     *
     * Defaults to the indicator class name.
     *
     * @return string
     */
    public function get_codename() {
        $fullclassname = get_class($this);
        return substr($fullclassname, strrpos($fullclassname, '\\') + 1);
    }

}
