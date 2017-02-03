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
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses extends sitewide {

    public function get_samples_origin() {
        return 'course';
    }

    protected function get_samples(\tool_inspire\analysable $site) {
        global $DB;

        // Getting courses from DB instead of from the site as these samples
        // will be stored in memory and we just want the id.
        $select = 'id != 1';
        $courses = $DB->get_records_select('course', $select, null, '', '*');

        $courseids = array_keys($courses);
        $sampleids = array_combine($courseids, $courseids);

        // No related data attached.
        return array($sampleids, array('course' => $courses));
    }
}
