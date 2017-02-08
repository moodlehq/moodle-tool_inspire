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
 * Renderer.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use templatable;
use renderable;

/**
 * Renderer class.
 *
 * @package    tool_inspire
 * @copyright  2016 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param templatable $renderable
     * @return string HTML
     */
    public function render_models_list(templatable $renderable) {
        $data = $renderable->export_for_template($this);
        return parent::render_from_template('tool_inspire/models_list', $data);
    }

    public function render_predictions_list(renderable $renderable) {

        $output = '';

        $data = $renderable->export_for_template($this);

        // No predictions_list.mustache because we can't have dynamic partial names.
        foreach ($data->predictions as $sampleid => $prediction) {
            $output .= parent::render_from_template($data->templatename, $prediction);
        }

        return $output;
    }
}
