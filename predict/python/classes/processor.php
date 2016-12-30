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
 * Python predictions processor
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace predict_python;

defined('MOODLE_INTERNAL') || die();

/**
 * Research tool site manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processor {

    const VALIDATION = 0.7;
    const DEVIATION = 0.02;
    const ITERATIONS = 30;

    function evaluate_dataset($datasetpath, $outputdir) {

        mtrace('Evaluating ' . $datasetpath . ' dataset');

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR .
            'check-classification-singleclass.py');

        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($datasetpath) . ' ' .
            escapeshellarg(self::VALIDATION) . ' ' .
            escapeshellarg(self::DEVIATION) . ' ' .
            escapeshellarg(self::ITERATIONS);

        $output = null;
        $exitcode = null;
        $result = exec($cmd, $output, $exitcode);

        if (!$result) {
            throw new \moodle_exception('errornopredictresults', 'tool_research');
        }


        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errorpredictwrongformat', 'tool_research', '', json_last_error_msg());
        }

        return $resultobj;
    }
}
