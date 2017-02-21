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
 * Weekly time splitting method.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\time_splitting;

defined('MOODLE_INTERNAL') || die();

class weekly extends base {

    public function get_name() {
        return get_string('timesplitting:weekly', 'tool_inspire');
    }

    public function is_valid_analysable(\tool_inspire\analysable $analysable) {
        $diff = $analysable->get_end() - $analysable->get_start();
        $nweeks = round($diff / WEEKSECS);
        if ($nweeks > 520) {
            // More than 10 years...
            return false;
        }
        return parent::is_valid_analysable($analysable);
    }

    protected function define_ranges() {

        $ranges = array();

        // It is more important to work with a proper end date than the start date.
        $i = 0;
        do {

            $dt = new \DateTime();
            $dt->setTimestamp($this->analysable->get_end());
            $dt->modify('-' . $i . ' weeks');
            $rangeend = $dt->getTimestamp();

            $dt->modify('-1 weeks');
            $rangestart = $dt->getTimestamp();

            $ranges[] = array(
                'start' => $rangestart,
                'end' => $rangeend
            );

            $i++;

        } while ($this->analysable->get_start() < $rangestart);

        $ranges = array_reverse($ranges, false);

        // Is not worth trying to predict during the first weeks.
        array_shift($ranges);
        array_shift($ranges);

        return $ranges;
    }

}
