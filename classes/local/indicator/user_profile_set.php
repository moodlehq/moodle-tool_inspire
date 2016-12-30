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
 * User profile set indicator.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * User profile set indicator.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile_set extends base {

    public static function get_requirements() {
        return ['user'];
    }

    public function calculate_row($row, $data, $starttime = false, $endtime = false) {
        $user = $data['user'][$row];

        // Nothing set results in -1.
        $calculatedvalue = self::MIN_VALUE;

        if (!$user->policyagreed) {
            return self::MIN_VALUE;
        }

        if (!$user->confirmed) {
            return self::MIN_VALUE;
        }

        if ($user->description != '') {
            $calculatedvalue += 1;
        }

        if ($user->picture != '') {
            $calculatedvalue += 1;
        }

        // 0.2 for any of the following fields being set (some of them may even be compulsory or have a default).
        $fields = array('institution', 'department', 'address', 'city', 'country', 'url');
        foreach ($fields as $fieldname) {
            if ($user->{$fieldname} != '') {
                $calculatedvalue += 0.2;
            }
        }

        return $this->limit_value($calculatedvalue);
    }
}
