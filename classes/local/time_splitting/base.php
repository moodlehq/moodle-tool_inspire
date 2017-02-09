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
 * Base time splitting method.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\time_splitting;

defined('MOODLE_INTERNAL') || die();

abstract class base {

    /**
     * @var string
     */
    protected $codename;

    /**
     * @var \tool_inspire\analysable
     */
    protected $analysable;


    /**
     * @var int[]
     */
    protected $sampleids;

    /**
     * @var string
     */
    protected $samplesorigin;

    /**
     * @var array
     */
    protected $ranges = [];

    /**
     * @var \tool_inspire\indicator\base
     */
    protected static $indicators = [];

    abstract protected function define_ranges();

    /**
     * Returns the time splitting method codename.
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

    public function get_analysable() {
        return $this->analysable;
    }

    /**
     * Returns whether the course can be processed by this time splitting method or not.
     *
     * @return bool
     */
    public function is_valid_analysable(\tool_inspire\analysable $analysable) {
        return true;
    }

    public function ready_to_predict($range) {
        if ($range['end'] <= time()) {
            return true;
        }
        return false;
    }

    /**
     * Calculates the course students indicators and targets.
     *
     * @return void
     */
    public function calculate($sampleids, $samplesorigin, $indicators, $ranges, $target = false) {

        $calculatedtarget = false;
        if ($target) {
            // We first calculate the target because analysable data may still be invalid, we need to stop if it is not.
            $calculatedtarget = $target->calculate($sampleids, $this->analysable);

            // We remove samples we can not calculate their target.
            $sampleids = array_filter($sampleids, function($sampleid) use ($calculatedtarget) {
                if (is_null($calculatedtarget[$sampleid])) {
                    return false;
                }
                return true;
            });
        }

        // No need to continue calculating if the target couldn't be calculated for any sample.
        if (empty($sampleids)) {
            return false;
        }

        $dataset = $this->calculate_indicators($sampleids, $samplesorigin, $indicators, $ranges);

        // Now that we have the indicators in place we can add the time range indicators (and target if provided) to each of them.
        $this->fill_dataset($dataset, $calculatedtarget);

        $this->add_metadata($dataset, $indicators, $target);

        return $dataset;
    }

    /**
     * Calculates indicators for all course students.
     *
     * @return array
     */
    protected function calculate_indicators($sampleids, $samplesorigin, $indicators, $ranges) {

        $dataset = array();

        // Fill the dataset samples with indicators data.
        foreach ($indicators as $indicator) {

            // Per-range calculations.
            foreach ($ranges as $rangeindex => $range) {

                // Calculate the indicator for each sample in this time range.
                $calculated = $indicator->calculate($sampleids, $samplesorigin, $range['start'], $range['end']);

                // Copy the calculated data to the dataset.
                foreach ($calculated as $analysersampleid => $calculatedvalues) {

                    $uniquesampleid = $this->append_rangeindex($analysersampleid, $rangeindex);

                    // Init the sample if it is still empty.
                    if (!isset($dataset[$uniquesampleid])) {
                        $dataset[$uniquesampleid] = array();
                    }

                    // Append the calculated indicator features at the end of the sample.
                    $dataset[$uniquesampleid] = array_merge($dataset[$uniquesampleid], $calculatedvalues);
                }
            }
        }

        return $dataset;
    }

    /**
     * Adds time range indicators and the target to each sample.
     *
     * This will identify the sample as belonging to a specific range.
     *
     * @return void
     */
    protected function fill_dataset(&$dataset, $calculatedtarget = false) {

        $nranges = count($this->get_all_ranges());

        foreach ($dataset as $uniquesampleid => $unmodified) {

            list($analysersampleid, $rangeindex) = $this->infer_sample_info($uniquesampleid);

            // No need to add range features if this time splitting method only defines one time range.
            if ($nranges > 1) {

                // 1 column for each range.
                $timeindicators = array_fill(0, $nranges, 0);

                $timeindicators[$rangeindex] = 1;

                $dataset[$uniquesampleid] = array_merge($timeindicators, $dataset[$uniquesampleid]);
            }

            if ($calculatedtarget) {
                // Add this sampleid's calculated target and the end.
                $dataset[$uniquesampleid][] = $calculatedtarget[$analysersampleid];

            } else {
                // Add this sampleid, it will be used to identify the prediction that comes back from
                // the predictions processor.
                array_unshift($dataset[$uniquesampleid], $uniquesampleid);
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
    protected function add_metadata(&$dataset, $indicators, $target = false) {

        $metadata = array(
            'timesplitting' => $this->get_codename(),
            // If no target the first column is the sampleid, if target the last column is the target.
            'nfeatures' => count(current($dataset)) - 1
        );
        if ($target) {
            $metadata['targetclasses'] = json_encode($target::get_classes());
            $metadata['targettype'] = ($target->is_linear()) ? 'linear' : 'discrete';
        }

        // The first 2 samples will be used to store metadata about the dataset.
        $metadatacolumns = [];
        $metadatavalues = [];
        foreach ($metadata as $key => $value) {
            $metadatacolumns[] = $key;
            $metadatavalues[] = $value;
        }

        $headers = $this->get_headers($indicators, $target);

        // This will also reset samples' dataset keys.
        array_unshift($dataset, $metadatacolumns, $metadatavalues, $headers);
    }

    /**
     * Returns the ranges used by this time splitting method.
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

    public function append_rangeindex($sampleid, $rangeindex) {
        return $sampleid . '-' . $rangeindex;
    }

    public function infer_sample_info($sampleid) {
        return explode('-', $sampleid);
    }

    protected function get_headers($indicators, $target = false) {
        // 3th column will contain the indicators codenames.
        $headers = array();

        if (!$target) {
            // The first column is the sampleid.
            $headers[] = 'sampleid';
        }

        // We always have 1 column for each time splitting method range, it does not depend on how
        // many ranges we calculated.
        $ranges = $this->get_all_ranges();
        if (count($ranges) > 1) {
            foreach ($ranges as $rangeindex => $range) {
                // Starting from 1 when displaying it.
                $headers[] = 'range-' . ($rangeindex + 1);
            }
        }

        // Model indicators.
        foreach ($indicators as $indicator) {
            $headers = array_merge($headers, $indicator::get_feature_headers());
        }

        // The target as well.
        if ($target) {
            $headers[] = clean_param($target::get_codename(), PARAM_ALPHANUMEXT);
        }

        return $headers;
    }

    /**
     * Validates the time splitting method ranges.
     *
     * @throw \coding_exception
     * @return void
     */
    protected function validate_ranges() {
        foreach ($this->ranges as $key => $range) {
            if (!isset($this->ranges[$key]['start']) || !isset($this->ranges[$key]['end'])) {
                throw new \coding_exception($this->get_codename() . ' time splitting method "' . $key .
                    '" range is not fully defined. We need a start timestamp and an end timestamp.');
            }
        }
    }
}
