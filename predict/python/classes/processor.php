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
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace predict_python;

defined('MOODLE_INTERNAL') || die();

/**
 * Python predictions processor.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processor implements \tool_inspire\predictor {

    const VALIDATION = 0.7;
    const DEVIATION = 0.02;
    const ITERATIONS = 30;

    public function train($uniqueid, $datasetpath, $outputdir) {

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR .
            'train-classification-singleclass.py');

        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($uniqueid) . ' ' .
            escapeshellarg($outputdir) . ' ' .
            escapeshellarg($datasetpath);

        if (debugging() && !PHPUNIT_TEST) {
            mtrace($cmd);
        }

        $output = null;
        $exitcode = null;
        $result = exec($cmd, $output, $exitcode);

        if (!$result) {
            throw new \moodle_exception('errornopredictresults', 'tool_inspire');
        }

        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errorpredictwrongformat', 'tool_inspire', '', json_last_error_msg());
        }

        if ($exitcode != 0) {
            throw new \moodle_exception('errorpredictionsprocessor', 'tool_inspire', '', implode(', ', $resultobj->errors));
        }

        return $resultobj;
    }

    public function predict($uniqueid, $datasetpath, $outputdir) {

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR .
            'predict-classification-singleclass.py');

        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($uniqueid) . ' ' .
            escapeshellarg($outputdir) . ' ' .
            escapeshellarg($datasetpath);

        if (debugging() && !PHPUNIT_TEST) {
            mtrace($cmd);
        }

        $output = null;
        $exitcode = null;
        $result = exec($cmd, $output, $exitcode);

        if (!$result) {
            throw new \moodle_exception('errornopredictresults', 'tool_inspire');
        }

        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errorpredictwrongformat', 'tool_inspire', '', json_last_error_msg());
        }

        if ($exitcode != 0) {
            throw new \moodle_exception('errorpredictionsprocessor', 'tool_inspire', '', implode(', ', $resultobj->errors));
        }

        return $resultobj;
    }

    public function evaluate($uniqueid, $datasetpath, $outputdir) {

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR .
            'evaluate-classification-singleclass.py');
        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($uniqueid) . ' ' .
            escapeshellarg($outputdir) . ' ' .
            escapeshellarg($datasetpath) . ' ' .
            escapeshellarg(self::VALIDATION) . ' ' .
            escapeshellarg(self::DEVIATION) . ' ' .
            escapeshellarg(self::ITERATIONS);

        if (debugging() && !PHPUNIT_TEST) {
            mtrace($cmd);
        }

        $output = null;
        $exitcode = null;
        $result = exec($cmd, $output, $exitcode);

        if (!$result) {
            throw new \moodle_exception('errornopredictresults', 'tool_inspire');
        }

        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errorpredictwrongformat', 'tool_inspire', '', json_last_error_msg());
        }

        // Phi goes from 0 to 1 so its value is the model score for this range processor.
        $resultobj->score = $resultobj->phi;

        return $resultobj;
    }
}
