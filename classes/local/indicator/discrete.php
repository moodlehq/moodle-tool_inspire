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
 * Abstract discrete indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract discrete indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class discrete extends base {

    /**
     * Classes need to be defined so they can be converted internally to individual dataset features.
     *
     * @return string[]
     */
    protected static function get_classes() {
        throw new \coding_exception('Please overwrite get_classes() specifying your discrete-values\' indicator classes');
    }

    public static function get_feature_headers() {
        $codename = clean_param(static::get_codename(), PARAM_ALPHANUMEXT);

        $headers = array($codename);
        foreach (self::get_classes() as $class) {
            $headers[] = $codename . '-' . $class;
        }

        return $headers;
    }

    protected function to_features($calculatedvalues) {

        $classes = self::get_classes();

        foreach ($calculatedvalues as $sampleid => $calculatedvalue) {

            $classindex = array_search($calculatedvalue, $classes, true);

            if (!$classindex) {
                throw new \coding_exception(get_class($this) . ' calculated "' . $calculatedvalue .
                    '" which is not one of its defined classes (' . json_encode($classes) . ')');
            }

            // We transform the calculated value into multiple features, one for each of the possible classes.
            $features = array_fill(0, count($classes), 0);

            // 1 to the selected value.
            $features[$classindex] = 1;

            $calculatedvalues[$sampleid] = $features;
        }

        return $calculatedvalues;
    }
}
