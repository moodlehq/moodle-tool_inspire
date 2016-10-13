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
 * 4 quarters range processor.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\range_processor;

defined('MOODLE_INTERNAL') || die();

/**
 * 4 quarters range processor.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class simple_quarters extends base {

    protected function define_ranges() {
        $duration = floor(($this->courseendtime - $this->coursestarttime) / 4);
        return [
            [
                'id' => 1,
                'start' => $this->coursestarttime,
                'end' => $this->coursestarttime + $duration
            ], [
                'id' => 2,
                'start' => $this->coursestarttime + $duration,
                'end' => $this->coursestarttime + ($duration * 2)
            ], [
                'id' => 3,
                'start' => $this->coursestarttime + ($duration * 2),
                'end' => $this->coursestarttime + ($duration * 3)
            ], [
                'id' => 4,
                'start' => $this->coursestarttime + ($duration * 3),
                'end' => $this->coursestarttime + ($duration * 4)
            ]
        ];
    }
}
