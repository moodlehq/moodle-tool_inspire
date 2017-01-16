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
 * Base range processor.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\range_processor;

defined('MOODLE_INTERNAL') || die();

abstract class base {

    /**
     * @var string
     */
    protected $codename;

    /**
     * @var \stdClass
     */
    protected $analysable;


    /**
     * @var array
     */
    protected $samples;

    /**
     * @var array
     */
    protected $ranges = [];

    /**
     * Array used to store all db records indicators are interested in using.
     *
     * This will be big, indicators should be concerned about performance, as they
     * may increase memory usage significantly if they require text fields.
     *
     * @var []
     */
    protected $storage = [];

    /**
     * @var \tool_inspire\indicator\base
     */
    protected static $indicators = [];

    abstract protected function define_ranges();

    /**
     * Returns the range processor codename.
     *
     * @return string
     */
    public function get_codename() {
        if (empty($this->codename)) {
            $fullclassname = get_class($this);
            $this->codename = substr($fullclassname, strrpos($fullclassname, '\\') + 1);
        }

        return $this->codename;
    }

    public function set_analysable(\tool_inspire\analysable $analysable) {
        $this->analysable = $analysable;
    }

    public function set_samples($samples) {
        $this->samples = $samples;
    }

    /**
     * Returns whether the course can be processed by this range processor or not.
     *
     * @return bool
     */
    public function is_valid_analysable() {
        return true;
    }

    public function ready_to_predict($range) {
        if ($range['end'] < time()) {
            return true;
        }
        return false;
    }

    /**
     * Calculates the course students indicators and targets.
     *
     * @return void
     */
    public function calculate($indicators, $ranges, $target = false) {

        // We load the usual data required to analyse the analyser (e.g. if it is a course teachers and students may be a
        // popular requirement.
        $this->storage = $this->analysable->get_usual_required_records();

        // We now load all data required by the indicators and the target. Indicators and targets should be
        // aware that they shouldn't load a crazy amount of data because of memory usage possible problems.
        // In any case we run this in per-analysable batches, which means that we are not going to get all
        // site courses data at the same time but in batches.

        // Fetch all database items required records.
        foreach ($indicators as $indicator) {
            if ($requiredrecords = $indicator->get_required_records($this->analysable)) {
                $this->merge_into_storage($requiredrecords);
            }
        }

        // We only add target required records if we calculate the target.
        if ($target) {
            if ($requiredrecords = $target->get_required_records($this->analysable)) {
                $this->merge_into_storage($requiredrecords);
            }
        }

        $calculatedtarget = false;
        if ($target) {
            // We first calculate the target because analysable data may still be invalid, we need to stop if it is not.
            $calculatedtarget = $target->calculate($this->samples, $this->analysable, $this->storage);

            // We remove samples we can not calculate their target.
            $this->samples = array_filter($this->samples, function($sample) use ($calculatedtarget) {
                if (is_null($calculatedtarget[$sample])) {
                    return false;
                }
                return true;
            });
        }

        // No need to continue calculating if the target couldn't be calculated for any sample.
        if (empty($this->samples)) {
            return false;
        }

        // Empty dataset, passed by reference below.
        $dataset = [];

        $this->calculate_indicators($dataset, $indicators, $ranges);

        // Now that we have the indicators in place we can add the range indicators (and target if provided) to each of them.
        $this->fill_dataset($dataset, $ranges, $calculatedtarget);

        $this->add_metadata($dataset, $indicators, $ranges, $target);

        return $dataset;
    }

    /**
     * Calculates indicators for all course students.
     *
     * @return void
     */
    protected function calculate_indicators(&$dataset, $indicators, $ranges) {

        // Fill the dataset samples with indicators data.
        foreach ($indicators as $indicator) {

            // Per-range calculations.
            foreach ($ranges as $rangeindex => $range) {

                // Calculate the indicator for each sample in this time range.
                $calculated = $indicator->calculate($this->samples, $this->analysable, $this->storage, $range['start'], $range['end']);

                // Copy the calculated data to the dataset.
                foreach ($calculated as $analysersampleid => $calculatedvalue) {

                    $uniquesampleid = $this->append_rangeindex($analysersampleid, $rangeindex);

                    // Init the sample if it is still empty.
                    if (!isset($dataset[$uniquesampleid])) {
                        $dataset[$uniquesampleid] = [];
                    }

                    // Append the indicator at the end of the sample.
                    $dataset[$uniquesampleid][] = $calculatedvalue;
                }
            }
        }
    }

    /**
     * Adds time range indicators and the target to each sample.
     *
     * This will identify the sample as belonging to a specific range.
     *
     * @return void
     */
    protected function fill_dataset(&$dataset, $ranges, $calculatedtarget = false) {

        $nranges = count($ranges);

        foreach ($dataset as $uniquesampleid => $sample) {

            list($analysersampleid, $rangeindex) = $this->infer_sample_info($uniquesampleid);

            if ($calculatedtarget) {
                // Add this sampleid's calculated target.
                $dataset[$uniquesampleid][] = $calculatedtarget[$analysersampleid];
            }

            // No need to add range features if there is only 1 range.
            if ($nranges > 1) {

                // 1 column for each range.
                $rangeindicators = array_fill(0, $nranges, 0);

                $rangeindicators[$rangeindex] = 1;

                $dataset[$uniquesampleid] = array_merge($rangeindicators, $dataset[$uniquesampleid]);
            }
        }
    }

    /**
     * Adds dataset context info.
     *
     * The final dataset document will look like this:
     * ----------------------------------------------------
     * metadata1,metadata2,metadata3,.....
     * value1, value2, value3,.....
     *
     * indicator1,indicator2,indicator3,indicator4,.....
     * stud1value1,stud1value2,stud1value3,stud1value4,.....
     * stud2value1,stud2value2,stud2value3,stud2value4,.....
     * .....
     * ----------------------------------------------------
     *
     * @return void
     */
    protected function add_metadata(&$dataset, $indicators, $ranges, $target = false) {

        // Metadata is mainly provided by the analysable.
        $metadata = array(
            'rangeprocessor' => $this->get_codename(),
            'nfeatures' => count(current($dataset)) - 1, // We skip the target column.
            'targetclasses' => json_encode($target->get_classes()),
            'targettype' => ($target->is_linear()) ? 'linear' : 'discrete'
        );
        $metadata = array_merge($metadata, $this->analysable->get_metadata());

        // The first 2 samples will be used to store metadata about the dataset.
        $metadatacolumns = [];
        $metadatavalues = [];
        foreach ($metadata as $key => $value) {
            $metadatacolumns[] = $key;
            $metadatavalues[] = $value;
        }

        $headers = $this->get_columns_headers($indicators, $ranges, $target);

        // This will also reset samples' dataset keys.
        array_unshift($dataset, $metadatacolumns, $metadatavalues, $headers);
    }

    /**
     * Returns the ranges used by this range processor.
     *
     * @return array
     */
    public function get_all_ranges() {
        if (empty($this->ranges)) {
            $this->ranges = $this->define_ranges();
            $this->validate_ranges();
        }
        return $this->ranges;
    }

    protected function append_rangeindex($sampleid, $rangeindex) {
        return $sampleid . '-' . $rangeindex;
    }

    protected function infer_sample_info($sampleid) {
        return explode('-', $sampleid);
    }

    protected function get_columns_headers($indicators, $ranges, $target = false) {
        // 3rd column will be empty (dataset readability).
        // 4th column will contain the indicators codenames.
        $headers = [];

        // Range indicators.
        if (count($ranges) > 1) {
            foreach ($ranges as $rangeindex => $range) {
                // Starting from 1 when displaying it.
                $headers[] = 'range-' . ($rangeindex + 1);
            }
        }

        // Model indicators.
        foreach ($indicators as $indicator) {
            $headers[] = clean_param($indicator::get_name(), PARAM_ALPHANUMEXT);
        }

        // The target as well.
        if ($target) {
            $headers[] = clean_param($target::get_name(), PARAM_ALPHANUMEXT);
        }

        return $headers;
    }

    /**
     * Merge the required info the indicator specified into the internal cache.
     *
     * @param array $info
     * @return void
     */
    protected function merge_into_storage($info) {

        foreach ($info as $tablename => $records) {

            if (empty($this->storage[$tablename])) {
                // New table info - just move there all new records.
                $this->storage[$tablename] = $records;
            } else {
                // Merge records.

                foreach ($records as $id => $record) {
                    if (empty($this->storage[$tablename][$id])) {
                        // Add new records if no records present with that id.
                        $this->storage[$tablename][$id] = $record;
                    } else {
                        // Merge objects if that object already have some fields.

                        // TODO We could have a tablename+id mapping to crc32 hashes so we don't need
                        // to calculate them all again.
                        if (crc32(json_encode($this->storage[$tablename][$id])) === crc32(json_encode($record))) {
                            // Skip the record if it is exactly the same one we already have.
                            continue;
                        }

                        // This should be safe as indicators are not allowed to modify the database contents in
                        // get_required_records and we call all get_required_records methods sequentially.
                        //
                        // In case any indicator has the great idea of modifying the database and returning a modified record
                        // we give preference to the value that was already set so the issue should be detected by the indicator
                        // that modified the object, not the previous one.
                        //
                        // TODO We could improve this merge robustness by throwing an exception if any field value do not match
                        // but the cost is performance.
                        $this->storage[$tablename][$id] = (object) (array)$this->storage[$tablename][$id] + (array)$record;
                    }
                }
            }
        }
    }

    /**
     * Validates the range processor ranges.
     *
     * @throw \coding_exception
     * @return void
     */
    protected function validate_ranges() {
        foreach ($this->ranges as $key => $range) {
            if (!isset($this->ranges[$key]['start']) || !isset($this->ranges[$key]['end'])) {
                throw new \coding_exception($this->get_codename() . ' range processor "' . $key .
                    '" range is not fully defined. We need an id, a start and an end.');
            }
        }
    }
}
