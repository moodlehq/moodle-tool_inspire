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
 * Cognitive depth indicator - forum.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator\forum;

defined('MOODLE_INTERNAL') || die();

/**
 * Cognitive depth indicator - forum.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cognitive_depth extends \tool_inspire\local\indicator\activity_cognitive_depth {

    public static function get_name() {
        return get_string('indicator:cognitivedepthforum', 'tool_inspire');
    }

    protected function get_activity_type() {
        return 'forum';
    }

    public function get_cognitive_depth_level(\cm_info $cm) {
        return 4;
    }

    protected function feedback_check_grades() {
        return false;
    }

    protected function feedback_viewed_events() {
        // We could add any forum event, but it will make feedback_post_action slower
        return array('\mod_forum\event\assessable_uploaded', '\mod_forum\event\course_module_viewed', '\mod_forum\event\discussion_viewed');
    }

    protected function feedback_replied_events() {
        return array('\mod_forum\event\assessable_uploaded');
    }

    protected function feedback_post_action(\cm_info $cm, $contextid, $userid, $eventnames, $after = false) {

        if (empty($this->activitylogs[$contextid][$userid])) {
            return false;
        }

        $logs = $this->activitylogs[$contextid][$userid];

        if (empty($logs['\mod_forum\event\assessable_uploaded'])) {
            // No feedback viewed if there is no submission.
            return false;
        }

        // First user post time.
        $firstpost = $logs['\mod_forum\event\assessable_uploaded']->timecreated[0];

        // We consider feedback any other user post in any of this forum discussions.
        foreach ($this->activitylogs[$contextid] as $anotheruserid => $logs) {
            if ($anotheruserid == $userid) {
                continue;
            }
            if (empty($logs['\mod_forum\event\assessable_uploaded'])) {
                continue;
            }
            $firstpostsenttime = $logs['\mod_forum\event\assessable_uploaded']->timecreated[0];

            if (parent::feedback_post_action($cm, $contextid, $userid, $eventnames, $firstpostsenttime)) {
                return true;
            }
            // Continue with the next user.
        }

        return false;
    }
}
