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
 * Cognitive depth indicator - folder.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator\folder;

defined('MOODLE_INTERNAL') || die();

/**
 * Cognitive depth indicator - folder.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cognitive_depth extends \tool_inspire\local\indicator\linear {

    /**
     * @var \stdClass[]
     */
    protected $rangeactivities = array();

    public static function get_name() {
        return get_string('indicator:cognitivedepthfolder', 'tool_inspire');
    }

    public function calculate_sample($sampleid, $tablename, $data, $starttime = false, $endtime = false) {
        global $DB;

        $select = '';
        $params = array();

        $user = $this->retrieve('user', $sampleid);
        $course = new \tool_inspire\course($this->retrieve('course', $sampleid));

        // Filter by context to use the db table index.
        $context = $course->get_context();
        $select .= "contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid AND " .
            "(crud = 'c' OR crud = 'u') AND timecreated > :starttime AND timecreated <= :endtime";
        $params = $params + array('contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid, 'starttime' => $starttime, 'endtime' => $endtime);
        return $DB->record_exists_select('logstore_standard_log', $select, $params) ? self::get_max_value() : self::get_min_value();
    }
}
