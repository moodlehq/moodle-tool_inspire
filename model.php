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
require_capability('moodle/site:config', $context);

$model = new \tool_inspire\model($id);

$params = array('id' => $id, 'action' => $action);
$url = new \moodle_url('/admin/tool/inspire/model.php', $params);

switch ($action) {

    case 'edit':
        $title = get_string('editmodel', 'tool_inspire');
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
            'indicators' => \tool_inspire\manager::get_all_indicators(),
            'timesplittings' => \tool_inspire\manager::get_enabled_time_splitting_methods()
        );
        $mform = new \tool_inspire\output\form\edit_model(null, $customdata);

        if ($mform->is_cancelled()) {
            redirect(new \moodle_url('/admin/tool/inspire/index.php'));

        } else if ($data = $mform->get_data()) {
            confirm_sesskey();
            $model->update($data->enabled, $data->indicators, $data->timesplitting);
            redirect(new \moodle_url('/admin/tool/inspire/index.php'));
        }

        echo $OUTPUT->header();

        $modelobj = $model->get_model_obj();
        $modelobj->indicators = json_decode($modelobj->indicators);
        $mform->set_data($modelobj);
        $mform->display();
        break;

    case 'evaluate':
        echo $OUTPUT->header();
        $results = $model->evaluate();
        $renderer = $PAGE->get_renderer('tool_inspire');
        echo $renderer->render_evaluate_results($results);
        break;

    case 'execute':
        echo $OUTPUT->header();
        $model->train();
        $results = $model->predict();
        $renderer = $PAGE->get_renderer('tool_inspire');
        echo $renderer->render_predict_results($results);
        break;

    case 'log':
        echo $OUTPUT->header();
        $renderer = $PAGE->get_renderer('tool_inspire');
        $modellogstable = new \tool_inspire\output\model_logs('model-' . $model->get_id(), $model->get_id());
        echo $renderer->render_table($modellogstable);
        break;
}

echo $OUTPUT->footer();
