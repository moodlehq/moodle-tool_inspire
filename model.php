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
 * Model-related actions.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);

$context = context_system::instance();

require_login();
require_capability('tool/inspire:managemodels', $context);

$model = new \tool_inspire\model($id);

$params = array('id' => $id, 'action' => $action);
$url = new \moodle_url('/admin/tool/inspire/model.php', $params);

switch ($action) {

    case 'edit':
        $title = get_string('editmodel', 'tool_inspire', $model->get_target()->get_name());
        break;
    case 'evaluate':
        $title = get_string('evaluatemodel', 'tool_inspire');
        break;
    case 'execute':
        $title = get_string('executemodel', 'tool_inspire');
        break;
    case 'log':
        $title = get_string('viewlog', 'tool_inspire');
        break;
    default:
        throw new moodle_exception('errorunknownaction', 'tool_inspire');
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($title);
$PAGE->set_heading($title);

switch ($action) {

    case 'edit':

        $customdata = array(
            'id' => $model->get_id(),
            'model' => $model,
            'indicators' => $model->get_potential_indicators(),
            'timesplittings' => \tool_inspire\manager::get_enabled_time_splitting_methods()
        );
        $mform = new \tool_inspire\output\form\edit_model(null, $customdata);

        if ($mform->is_cancelled()) {
            redirect(new \moodle_url('/admin/tool/inspire/index.php'));

        } else if ($data = $mform->get_data()) {
            confirm_sesskey();

            // Converting option names to class names.
            $indicators = array();
            foreach ($data->indicators as $indicator) {
                $indicatorclass = \tool_inspire\output\helper::option_to_class($indicator);
                $indicators[] = \tool_inspire\manager::get_indicator($indicatorclass);
            }
            $timesplitting = \tool_inspire\output\helper::option_to_class($data->timesplitting);
            $model->update($data->enabled, $indicators, $timesplitting);
            redirect(new \moodle_url('/admin/tool/inspire/index.php'));
        }

        echo $OUTPUT->header();

        $modelobj = $model->get_model_obj();

        $callable = array('\tool_inspire\output\helper', 'class_to_option');
        $modelobj->indicators = array_map($callable, json_decode($modelobj->indicators));
        $modelobj->timesplitting = \tool_inspire\output\helper::class_to_option($modelobj->timesplitting);
        $mform->set_data($modelobj);
        $mform->display();
        break;

    case 'evaluate':
        echo $OUTPUT->header();
        // Web interface is used by people who can not use CLI nor code stuff, always use
        // cached stuff as they will change the model through the web interface as well
        // which invalidates the previously analysed stuff.
        $results = $model->evaluate(array('reuseprevanalysed' => true));
        $renderer = $PAGE->get_renderer('tool_inspire');
        echo $renderer->render_evaluate_results($results, $model->get_analyser()->get_logs());
        break;

    case 'execute':
        echo $OUTPUT->header();

        $trainresults = $model->train();
        $trainlogs = $model->get_analyser()->get_logs();

        // Looks dumb to get a new instance but better be conservative.
        $model = new \tool_inspire\model($model->get_model_obj());
        $predictresults = $model->predict();
        $predictlogs = $model->get_analyser()->get_logs();

        $renderer = $PAGE->get_renderer('tool_inspire');
        echo $renderer->render_execute_results($trainresults, $trainlogs, $predictresults, $predictlogs);
        break;

    case 'log':
        echo $OUTPUT->header();
        $renderer = $PAGE->get_renderer('tool_inspire');
        $modellogstable = new \tool_inspire\output\model_logs('model-' . $model->get_id(), $model->get_id());
        echo $renderer->render_table($modellogstable);
        break;
}

echo $OUTPUT->footer();
