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

    public function train($uniqueid, \stored_file $dataset, $outputdir) {

        $datasetpath = $this->get_file_path($dataset);

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

    public function predict($uniqueid, \stored_file $dataset, $outputdir) {

        $datasetpath = $this->get_file_path($dataset);

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

    public function evaluate($uniqueid, $resultsdeviation, $niterations, \stored_file $dataset, $outputdir) {

        $datasetpath = $this->get_file_path($dataset);

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
            'cli' . DIRECTORY_SEPARATOR . 'evaluate-classification-singleclass.py');
        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($uniqueid) . ' ' .
            escapeshellarg($outputdir) . ' ' .
            escapeshellarg($datasetpath) . ' ' .
            escapeshellarg(0.6) . ' ' .
            escapeshellarg($resultsdeviation) . ' ' .
            escapeshellarg($niterations);

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

        return $resultobj;
    }

    protected function get_file_path(\stored_file $file) {

        // From moodle filesystem to the local file system.
        // This is not ideal, but there is no read access to moodle filesystem files.
        $dir = make_request_directory();
        $filepath = $file->copy_content_to_temp($dir);

        // Copy the evaluated dataset filepath to the result object.
        return $filepath;
    }
}
