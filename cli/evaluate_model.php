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
 * Evaluates the provided model.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help = "Evaluates the provided model.

Options:
--modelid           Model id
--non-interactive   Not interactive questions
--filter            Analyser dependant (Optional)
-h, --help          Print out this help

Example:
\$ php admin/tool/inspire/cli/evaluate_model.php --modelid=1 --filter=123,321
";

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help'            => false,
        'modelid'         => false,
        'non-interactive' => false,
        'filter'          => false
    ),
    array(
        'h' => 'help',
    )
);

if ($options['help']) {
    echo $help;
    exit(0);
}

if ($options['modelid'] === false) {
    echo $help;
    exit(0);
}

// Reformat them as an array.
if ($options['filter'] !== false) {
    $options['filter'] = explode(',', $options['filter']);
}

// We need admin permissions.
\core\session\manager::set_user(get_admin());

$modelobj = $DB->get_record('tool_inspire_models', array('id' => $options['modelid']), '*', MUST_EXIST);
$model = new \tool_inspire\model($modelobj);

mtrace(get_string('analysingsitedata', 'tool_inspire'));

$analyseroptions = array(
    'filter' => $options['filter'],
);
// Evaluate its suitability to predict accurately.
$results = $model->evaluate($analyseroptions);

foreach ($results as $timesplittingcodename => $result) {
    mtrace($timesplittingcodename . ' results');
    mtrace(' - status code: ' . $result->status);
    mtrace(' - score: ' . $result->score);
    if (!empty($result->errors)) {
        mtrace(' - errors');
        foreach ($result->errors as $error) {
            mtrace('   - ' . $error);
        }
    }
}

if ($options['non-interactive']) {

    // Select a dataset, train and enable the model.
    $input = cli_input(get_string('trainandenablemodel', 'tool_inspire'));
    $timesplittingcodename = clean_param($input, PARAM_ALPHANUMEXT);
    while (empty($results[$timesplittingcodename])) {
        mtrace(get_string('errorunexistingtimesplitting', 'tool_inspire'));
        $input = cli_input(get_string('trainandenablemodel', 'tool_inspire'));
        $timesplittingcodename = clean_param($input, PARAM_ALPHANUMEXT);
    }

    // Set the time splitting method file and enable it.
    $model->enable($timesplittingcodename);

    mtrace(get_string('trainingmodel', 'tool_inspire', $timesplittingcodename));

    // Train the model with the selected time splitting method.
    $model->train();
}

cli_heading(get_string('success'));
exit(0);
