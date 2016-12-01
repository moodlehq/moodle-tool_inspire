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
 * Research tool manager
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research;

defined('MOODLE_INTERNAL') || die();

/**
 * Research tool site manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model {

    const ANALYSE_OK = 0;
    const ANALYSE_GENERAL_ERROR = 1;
    const ANALYSE_INPROGRESS = 2;
    const ANALYSE_REJECTED_RANGE_PROCESSOR = 3;
    const ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS = 4;

    protected $model = null;

    public function __construct($model) {
        $this->model = $model;
    }

    public function get_target() {
        $classname = $this->model->target;
        return new $classname();
    }

    public function get_indicators() {

        $indicators = [];

        // TODO Read the model indicators instead of read all indicators in the folder.
        $classes = \core_component::get_component_classes_in_namespace('tool_research', 'indicator');
        foreach ($classes as $fullclassname => $classpath) {

            // Discard abstract classes and others.
            if (is_subclass_of($fullclassname, 'tool_research\indicator\base')) {
                if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                    $indicators[$fullclassname] = new $fullclassname();
                }
            }
        }

        return $indicators;
    }

    public function get_analyser($target, $indicators, $rangeprocessors) {
        // TODO Select it from any component.
        $classname = $target->get_analyser_class();

        // TODO Check class exists.
        return new $classname($this->model->id, $target, $indicators, $rangeprocessors);
    }

    /**
     * Get all available range processors.
     *
     * @return \tool_research\range_processor\base[]
     */
    public function get_range_processors() {

        // TODO: It should be able to search range processors in other plugins.
        $classes = \core_component::get_component_classes_in_namespace('tool_research', 'range_processor');

        $rangeprocessors = [];
        foreach ($classes as $fullclassname => $classpath) {
            if (self::is_a_valid_range_processor($fullclassname)) {
               $rangeprocessors[] = new $fullclassname();
            }
        }
        return $rangeprocessors;
    }

    /**
     * is_a_valid_range_processor
     *
     * @param string $fullclassname
     * @return bool
     */
    protected static function is_a_valid_range_processor($fullclassname) {
        if (is_subclass_of($fullclassname, '\tool_research\local\range_processor\base')) {
            if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analyses the model.
     *
     * @param  array   $options
     * @return array Status codes and generated files
     */
    public function analyse($options = array()) {

        $target = $this->get_target();
        $indicators = $this->get_indicators();
        $rangeprocessors = $this->get_range_processors();

        $analyser = $this->get_analyser($target, $indicators, $rangeprocessors);
        return $analyser->analyse($options);
    }
}
