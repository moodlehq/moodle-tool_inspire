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
 * Guesses course start and end dates based on activity logs.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help = "Guesses course start and end dates based on activity logs.

Options:
--guessstart           Guess the course start date (default to true)
--guessend             Guess the course end date (default to true)
--guessall             Guess all start and end dates, even if they are already set (default to false)
--update               Update the db or just notify the guess (default to false)
--filter               Analyser dependant. e.g. A courseid would evaluate the model using a single course (Optional)
-h, --help             Print out this help

Example:
\$ php admin/tool/inspire/cli/guess_course_start_and_end_dates.php --update=1 --filter=123,321
";

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help'        => false,
        'guessstart'  => true,
        'guessend'    => true,
        'guessall'    => false,
        'update'      => false,
        'filter'      => false
    ),
    array(
        'h' => 'help',
    )
);

if ($options['help']) {
    echo $help;
    exit(0);
}

if ($options['guessstart'] === false || $options['guessend'] === false || $options['guessall'] === false) {
    echo $help;
    exit(0);
}

// Reformat them as an array.
if ($options['filter'] !== false) {
    $options['filter'] = explode(',', clean_param($options['filter'], PARAM_SEQUENCE));
}

// We need admin permissions.
\core\session\manager::set_user(get_admin());

$conditions = array();
if (!$options['guessall']) {
    if ($options['guessstart']) {
        $conditions[] = '(startdate is null or startdate = 0)';
    }
    if ($options['guessend']) {
        $conditions[] = '(enddate is null or enddate = 0)';
    }
}

$coursessql = '';
$params = null;
if ($options['filter']) {
    list($coursessql, $params) = $DB->get_in_or_equal($options['filter'], SQL_PARAMS_NAMED);
    $conditions[] = 'id ' . $coursessql;
}

$courses = $DB->get_recordset_select('course', implode(' AND ', $conditions), $params, 'sortorder ASC');
foreach ($courses as $course) {
    $courseman = new \tool_inspire\course($course);

    $notification = $course->shortname . ' (id = ' . $course->id . '): ';

    if ($options['guessstart'] || $options['guessall']) {
        $startdate = $courseman->guess_start();
        if ($startdate) {
            $course->startdate = $startdate;
            $notification .= PHP_EOL . '  ' . get_string('startdate') . ': ' . userdate($course->startdate);
        } else {
            $notification .= PHP_EOL . '  ' . get_string('cantguessstartdate', 'tool_inspire');
        }
    }
    if ($options['guessend'] || $options['guessall']) {
        $enddate = $courseman->guess_end();
        if ($enddate) {
            $course->enddate = $enddate;
            $notification .= PHP_EOL . '  ' . get_string('enddate') . ': ' . userdate($course->enddate);
        } else {
            $notification .= PHP_EOL . '  ' . get_string('cantguessenddate', 'tool_inspire');
        }
    }

    echo $OUTPUT->notification($notification);

    if ($options['update']) {
        $DB->update_record('course', $course);
    }
}
$courses->close();

exit(0);
