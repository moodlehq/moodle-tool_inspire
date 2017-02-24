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
 * Model edit form.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output\form;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Model edit form.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_model extends \moodleform {

    /**
     * Form definition
     */
    public function definition () {
        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'tool_inspire'));

        $indicators = array();
        foreach ($this->_customdata['indicators'] as $classname => $indicator) {
            $indicators[$classname] = $indicator->get_name();
        }
        $options = array(
            'multiple' => true
        );
        $mform->addElement('autocomplete', 'indicators', get_string('indicators', 'tool_inspire'), $indicators, $options);

        $timesplittings = array('' => '');
        foreach ($this->_customdata['timesplittings'] as $classname => $timesplitting) {
            $timesplittings[$classname] = $timesplitting->get_name();
        }

        $mform->addElement('select', 'timesplitting', get_string('timesplittingmethod', 'tool_inspire'), $timesplittings);

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     *
     * @return array of errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['timesplitting'])) {
            if (\tool_inspire\manager::is_valid($data['timesplitting'], '\tool_inspire\local\time_splitting\base') === false) {
                $errors['timesplitting'] = get_string('errorinvalidtimesplitting', 'tool_inspire');
            }
        }

        if (empty($data['indicators'])) {
            $errors['indicators'] = get_string('errornoindicators', 'tool_inspire');
        } else {
            foreach ($data['indicators'] as $indicator) {
                if (\tool_inspire\manager::is_valid($indicator, '\tool_inspire\local\indicator\base') === false) {
                    $errors['indicators'] = get_string('errorinvalidindicator', 'tool_inspire', $indicator);
                }
            }
        }

        if (!empty($data['enabled']) && empty($data['timesplitting'])) {
            $errors['enabled'] = get_string('errorcantenablenotimesplitting', 'tool_inspire');
        }

        return $errors;
    }
}
