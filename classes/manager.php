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
 * Inspire tool manager.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 * Inspire tool manager.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * @var \tool_inspire\model[]
     */
    protected static $models = null;

    /**
     * @var \tool_inspire\predictor[]
     */
    protected static $predictionprocessors = null;

    /**
     * @var \tool_inspire\local\indicator\base[]
     */
    protected static $allindicators = null;

    /**
     * @var \tool_inspire\local\time_splitting\base[]
     */
    protected static $alltimesplittings = null;

    public static function get_all_models() {
        global $DB;

        if (self::$models !== null) {
            return self::$models;
        }

        $models = $DB->get_records('tool_inspire_models');
        foreach ($models as $model) {
            self::$models[$model->id] = new \tool_inspire\model($model);
        }
        return self::$models;
    }

    /**
     * Returns the site selected predictions processor.
     *
     * @return \tool_inspire\predictor
     */
    public static function get_predictions_processor($predictionclass = false) {

        if ($predictionclass === false) {
            $predictionclass = get_config('tool_inspire', 'predictionsprocessor');
        }

        if (empty($predictionclass)) {
            // Use the default one if nothing set.
            $predictionclass = '\predict_php\processor';
        }

        // Return it from the cached list.
        if (isset(self::$predictionprocessors[$predictionclass])) {
            return self::$predictionprocessors[$predictionclass];
        }

        if (!class_exists($predictionclass)) {
            throw new \coding_exception('Invalid predictions processor ' . $predictionclass . '.');
        }

        $interfaces = class_implements($predictionclass);
        if (empty($interfaces['tool_inspire\predictor'])) {
            throw new \coding_exception($predictionclass . ' should implement \tool_inspire\predictor.');
        }

        self::$predictionprocessors[$predictionclass] = new $predictionclass();

        return self::$predictionprocessors[$predictionclass];
    }

    public static function get_all_prediction_processors() {
        $subplugins = \core_component::get_subplugins('tool_inspire');

        $predictionprocessors = array();
        if (!empty($subplugins['predict'])) {
            foreach ($subplugins['predict'] as $subplugin) {
                $classfullpath = '\\predict_' . $subplugin . '\\processor';
                $predictionprocessors[$classfullpath] = self::get_predictions_processor($classfullpath);
            }
        }
        return $predictionprocessors;
    }

    /**
     * Get all available time splitting methods.
     *
     * @return \tool_inspire\time_splitting\base[]
     */
    public static function get_all_time_splittings() {
        if (self::$alltimesplittings !== null) {
            return self::$alltimesplittings;
        }

        // TODO: It should be able to search time splitting methods in other plugins.
        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\time_splitting');

        self::$alltimesplittings = [];
        foreach ($classes as $fullclassname => $classpath) {
            $instance = self::get_time_splitting($fullclassname);
            // We need to check that it is a valid time splitting method, it may be an abstract class.
            if ($instance) {
                self::$alltimesplittings[$instance->get_id()] = $instance;
            }
        }

        return self::$alltimesplittings;
    }

    /**
     * Returns the enabled time splitting methods.
     *
     * @return \tool_inspire\local\time_splitting\base[]
     */
    public static function get_enabled_time_splitting_methods() {

        if ($enabledtimesplittings = get_config('tool_inspire', 'timesplittings')) {
            $enabledtimesplittings = array_flip(explode(',', $enabledtimesplittings));
        }

        $timesplittings = self::get_all_time_splittings();
        foreach ($timesplittings as $key => $timesplitting) {

            // We remove the ones that are not enabled. This also respects the default value (all methods enabled).
            if (!empty($enabledtimesplittings) && !isset($enabledtimesplittings[$key])) {
                unset($timesplittings[$key]);
            }
        }
        return $timesplittings;
    }

    /**
     * Returns a time splitting method by its classname.
     *
     * @param string $fullclassname
     * @return \tool_inspire\local\time_splitting\base|false False if it is not valid.
     */
    public static function get_time_splitting($fullclassname) {
        if (!self::is_valid($fullclassname, '\tool_inspire\local\time_splitting\base')) {
            return false;
        }
        return new $fullclassname();
    }

    /**
     * Return all system indicators.
     *
     * @return \tool_inspire\local\indicator\base[]
     */
    public static function get_all_indicators() {
        if (self::$allindicators !== null) {
            return self::$allindicators;
        }

        self::$allindicators = [];

        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\indicator');
        foreach ($classes as $fullclassname => $classpath) {
            $instance = self::get_indicator($fullclassname);
            if ($instance) {
                // Using get_class as get_component_classes_in_namespace returns double escaped fully qualified class names.
                self::$allindicators['\\' . get_class($instance)] = $instance;
            }
        }

        return self::$allindicators;
    }

    public static function get_target($fullclassname) {
        if (!self::is_valid($fullclassname, 'tool_inspire\local\target\base')) {
            return false;
        }
        return new $fullclassname();
    }

    /**
     * Returns an instance of the provided indicator.
     *
     * @param string $fullclassname
     * @return \tool_inspire\local\indicator\base|false False if it is not valid.
     */
    public static function get_indicator($fullclassname) {
        if (!self::is_valid($fullclassname, 'tool_inspire\local\indicator\base')) {
            return false;
        }
        return new $fullclassname();
    }

    /**
     * Returns whether a time splitting method is valid or not.
     *
     * @param string $fullclassname
     * @return bool
     */
    public static function is_valid($fullclassname, $baseclass) {
        if (is_subclass_of($fullclassname, $baseclass)) {
            if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

}
