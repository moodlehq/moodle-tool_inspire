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
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\target;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base target.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_research\calculable {

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
     * @param \tool_research\analysable $analysable
     * @return bool
     */
    abstract public function is_valid(\tool_research\analysable $analysable);

    /**
     * Calculates this target for all course students.
     *
     * Returns an array of values which size matches $this->students. Use the userid as key.
     *
     * In case there are no values to return or the target is not applicable throw an exception as
     * this is not a valid model.
     *
     * @param stdClass $row
     * @param array $data
     * @return float
     */
    abstract protected function calculate_row($row, $data);
}
