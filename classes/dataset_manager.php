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
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/csvlib.class.php');

/**
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataset_manager {

    const FILEAREA = 'datasets';

    /**
     * The model id.
     *
     * @var int
     */
    protected $modelid;

    /**
     * Range processor in use.
     *
     * @var string
     */
    protected $rangeprocessor;

    /**
     * @var int
     */
    protected $analysableid;

    /**
     * Simple constructor.
     *
     * @return void
     */
    public function __construct($modelid, $analysableid, $rangeprocessorcodename) {
        $this->modelid = $modelid;
        $this->analysableid = $analysableid;
        $this->rangeprocessor = $rangeprocessorcodename;
    }

    /**
     * Mark the analysable as being analysed.
     *
     * @return void
     */
    public function init_process() {
        global $DB;

        // Delete the previous record if there is any.
        $params = array('modelid' => $this->modelid, 'analysableid' => $this->analysableid, 'rangeprocessor' => $this->rangeprocessor);
        $run = $DB->get_record('tool_research_runs', $params);
        if ($run) {
            if ($run->inprogress) {
                return \tool_research\site_manager::ANALYSE_INPROGRESS;
            }
            $DB->delete_records('tool_research_runs', $params);
        }

        $run = new \stdClass();
        $run->modelid = $this->modelid;
        $run->analysableid = $this->analysableid;
        $run->rangeprocessor = $this->rangeprocessor;
        $run->inprogress = 1;
        $run->timecompleted = 0;
        $run->id = $DB->insert_record('tool_research_runs', $run);

        // We delete the in progress record if there is an error during the process.
        \core_shutdown_manager::register_function(array($this, 'kill_in_progress'), $params);
    }

    /**
     * Store the dataset in the internal file system.
     *
     * @param array $data
     * @return \stored_file
     */
    public function store($data) {

        // Delete previous file if it exists.
        $fs = get_file_storage();
        $filerecord = [
            'component' => 'tool_research',
            'filearea' => self::FILEAREA,
            'itemid' => $this->analysableid,
            'contextid' => \context_system::instance()->id,
            'filepath' => '/' . $this->modelid . '/analysable',
            'filename' => $this->rangeprocessor . '.csv'
        ];
        $fs->delete_area_files($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'], $filerecord['itemid']);

        // Write all this stuff to a tmp file.
        $writer = new \csv_export_writer();
        $writer->set_filename($filerecord['filename']);

        foreach ($data as $row) {
            $writer->add_data($row);
        }

        // TODO: LOL, no fclose() for csv_export_writer::fp?
        return $fs->create_file_from_pathname($filerecord, $writer->path);
    }

    /**
     * Mark as analysed.
     *
     * @return void
     */
    public function close_process() {
        global $DB;

        $params = array('modelid' => $this->modelid, 'analysableid' => $this->analysableid,
            'rangeprocessor' => $this->rangeprocessor, 'inprogress' => 1);
        if (!$run = $DB->get_record('tool_research_runs', $params)) {
            throw new \moodle_exception('errornorunrecord', 'tool_research');
        }

        // Mark it as completed.
        $run->timecompleted = time();
        $run->inprogress = 0;
        $DB->update_record('tool_research_runs', $run);
    }

    /**
     * Kill any analysis that didn't finish properly.
     *
     * @param int $analysableid
     * @param string $rangeprocessor
     * @return void
     */
    public function kill_in_progress($modelid, $analysableid, $rangeprocessorcodename) {
        global $DB;

        // Kill in-progress runs if there is any.
        $params = array('modelid' => $modelid, 'analysableid' => $analysableid, 'rangeprocessor' => $rangeprocessorcodename,
            'inprogress' => 1, 'timecompleted' => 0);
        $DB->delete_records('tool_research_runs', $params);
    }

    public static function get_run($modelid, $analysableid, $rangeprocessorcodename) {
        global $DB;

        $params = array('modelid' => $modelid, 'analysableid' => $analysableid,
            'rangeprocessor' => $rangeprocessorcodename);
        return $DB->get_record('tool_research_runs', $params);
    }

    public static function get_analysable_file($modelid, $analysableid, $rangeprocessorcodename) {

        $fs = get_file_storage();
        return $fs->get_file(\context_system::instance()->id, 'tool_research', self::FILEAREA,
            $analysableid, '/' . $modelid . '/analysable', $rangeprocessorcodename . '.csv');
    }

    public static function get_range_file($modelid, $rangeprocessorcodename) {
        $fs = get_file_storage();
        return $fs->get_file(\context_system::instance()->id, 'tool_research', self::FILEAREA,
            $analysableid, '/' . $modelid . '/analysable', $rangeprocessorcodename . '.csv');
    }

    /**
     * Merge multiple files into one.
     *
     * Important! It is the caller responsability to ensure that the datasets are compatible.
     *
     * @param array $files
     * @return \stored_file
     */
    public static function merge_datasets(array $files, $modelid, $rangeprocessorcodename) {

        if (count($files) === 1) {
            return reset($files);
        }

        $tmpfilepath = make_request_directory() . DIRECTORY_SEPARATOR . 'tmpfile.csv';
        $wh = fopen($tmpfilepath, 'w');

        // Iterate through all files and add them to the tmp one. We don't want file contents in memory.
        foreach ($files as $file) {
            $rh = $file->get_content_file_handle();
            while ($line = fgets($rh, 40960)) {
                fwrite($wh, $line);
            }
        }

        $filerecord = [
            'component' => 'tool_research',
            'filearea' => self::FILEAREA,
            'itemid' => $this->convert_to_int($rangeprocessorcodename),
            'contextid' => \context_system::instance()->id,
            'filepath' => '/' . $modelid . '/range',
            'filename' => $rangeprocessorcodename . '.csv'
        ];

        return $fs->create_file_from_pathname($filerecord, $tmpfilepath);
    }

    /**
     * I know it is not very orthodox...
     *
     * @param string $string
     * @return int
     */
    protected function convert_to_int($string) {
        $sum = 0;
        for ($i = 0; $i < strlen($string); $i++) {
            $sum += ord($string[$i]);
        }
        return $sum;
    }
}
