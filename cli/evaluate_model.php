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
--model      Model code name
--analyseall Analyse all site or only non rencently analysed analysables
--filter     Analyser dependant (Optional)
-h, --help   Print out this help

Example:
\$ php admin/tool/inspire/cli/evaluate_model.php --filter=123,321
";

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help'          => false,
        'model'         => false,
        'analyseall'    => false,
        'filter'        => false
    ),
    array(
        'h' => 'help',
    )
);

if ($options['help']) {
    echo $help;
    exit(0);
}

// Reformat them as an array.
if ($options['filter'] !== false) {
    $options['filter'] = explode(',', $options['filter']);
}

echo "\n".get_string('processingcourses', 'tool_inspire')."\n\n";

$modelobj = $DB->get_record('tool_inspire_models', array('codename' => $options['model']), '*', MUST_EXIST);

$model = new \tool_inspire\model($modelobj);

// Build the dataset.
$analyseroptions = array('filter' => $options['filter'], 'analyseall' => $options['analyseall']);
$results = $model->build_dataset($analyseroptions);

foreach ($results['status'] as $analysableid => $statuscode) {
    mtrace('Analysable ' . $analysableid . ': Status code ' . $statuscode . '. ');
    if (!empty($results['messages'][$analysableid])) {
        mtrace(' - ' . $results['messages'][$analysableid]);
    }
}

// Evaluate its suitability to predict accurately.
$results = $model->evaluate();

foreach ($results as $rangeprocessorcodename => $result) {
    mtrace($rangeprocessorcodename . ' results');
    mtrace(' - status code: ' . $result->status);
    mtrace(' - score: ' . $result->score);
    if (!empty($result->errors)) {
        mtrace(' - errors');
        foreach ($result->errors as $error) {
            mtrace('   - ' . $error);
        }
    }
}

// Select a dataset, train and enable the model.
$input = cli_input(get_string('trainandenablemodel', 'tool_inspire'));
$rangeprocessorcodename = clean_param($input, PARAM_ALPHANUMEXT);
do {
    mtrace(get_string('errorunexistingrangeprocessor', 'tool_inspire'));
    $input = cli_input(get_string('trainandenablemodel', 'tool_inspire'));
    $rangeprocessorcodename = clean_param($input, PARAM_ALPHANUMEXT);
} while (empty($results[$rangeprocessorcodename]));

// Set the range processor file and enable it.
$model->enable($rangeprocessorcodename);

// Train the model with the selected range processor.
$model->train();

cli_heading(get_string('success'));
exit(0);
