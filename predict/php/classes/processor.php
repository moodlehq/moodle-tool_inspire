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
 * Php predictions processor
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace predict_php;

// TODO No support for 3rd party plugins psr4??
spl_autoload_register(function($class) {
    // Autoload Phpml classes.
    $path = __DIR__ . '/../phpml/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once($path);
    }
});

use Phpml\NeuralNetwork\Network\MultilayerPerceptron;
use Phpml\NeuralNetwork\Training\Backpropagation;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\Metric\Accuracy;

defined('MOODLE_INTERNAL') || die();

/**
 * PHP predictions processor.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processor implements \tool_inspire\predictor {

    public function train($uniqueid, $datasetpath, $outputdir) {
        throw new \Exception('Not implemented');
    }

    public function predict($uniqueid, $datasetpath, $outputdir) {
        throw new \Exception('Not implemented');
    }

    public function evaluate($uniqueid, $datasetpath, $outputdir) {

        mtrace('Evaluating ' . $datasetpath . ' dataset');

        $fh = fopen($datasetpath, 'r');

        // The first lines are var names and the second one values.
        $metadata = fgetcsv($fh);
        $metadata = array_combine($metadata, fgetcsv($fh));

        // Skip headers.
        fgets($fh);

        $network = new MultilayerPerceptron([intval($metadata['nfeatures']), 2, 1]);
        $training = new Backpropagation($network);

        $samples = array();
        $targets = array();
        while (($data = fgetcsv($fh)) !== false) {
            $sampledata = array_map('floatval', $data);
            $samples[] = array_slice($sampledata, 0, $metadata['nfeatures']);
            $targets[] = array(intval($data[$metadata['nfeatures']]));
        }
        fclose($fh);

        $dataset = new RandomSplit(new ArrayDataset($samples, $targets), 0.1);

        $training->train($dataset->getTrainSamples(), $dataset->getTrainLabels(), 0.3, 300);

        $predictedvalues = array();
        foreach ($dataset->getTestSamples() as $input) {
            $predictedvalues[] = $network->setInput($input)->getOutput();
        }
        var_dump(array_slice($dataset->getTestLabels(), 0, 20));
        var_dump(array_slice($predictedvalues, 0, 20));
die();
        $testlabels = array_reduce($dataset->getTestLabels(), 'array_merge', array());
        $predicted = array_reduce($predictedvalues, 'array_merge', array());
        return false;
        //die();
        if (!$result) {
            throw new \moodle_exception('errornopredictresults', 'tool_inspire');
        }


        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errorpredictwrongformat', 'tool_inspire', '', json_last_error_msg());
        }

        return $resultobj;
    }
}
