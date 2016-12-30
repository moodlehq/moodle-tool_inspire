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
 * Gather site courses indicators.
 *
 * @package    tool_research
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help = "Creates a model dataset:

Options:
--model      Model code name
--analyseall Analyse all site or only non rencently analysed analysables
--filter     Analyser dependant (Optional)
-h, --help   Print out this help

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/research/cli/model_dataset.php --filter=123,321
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

echo "\n".get_string('processingcourses', 'tool_research')."\n\n";

// TODO Set a DB table for this.
//$modelobj = $DB->get_record('tool_research_models', array('id' => $options['model']);

$modelobj = new \stdClass();
$modelobj->id = 1;
$modelobj->target = '\tool_research\local\target\grade_pass';
$model = new \tool_research\model($modelobj);

$analyseroptions = array('filter' => $options['filter'], 'analyseall' => $options['analyseall']);
$results = $model->build_dataset($analyseroptions);

foreach ($results['status'] as $analysableid => $statuscode) {
    mtrace('Analysable ' . $analysableid . ': Status code ' . $statuscode . '. ');
    if (!empty($results['messages'][$analysableid])) {
        mtrace(' - ' . $results['messages'][$analysableid]);
    }
}

$results = $model->evaluate();

foreach ($results as $rangeprocessorcodename => $data) {
    mtrace($rangeprocessorcodename . ' phi: ' . $data['results']->phi);
    if ($data['results']->errors) {
        foreach ($data['results']->errors as $error) {
            mtrace(' - ' . $error);
        }
    }
}

cli_heading(get_string('success'));
exit(0);
