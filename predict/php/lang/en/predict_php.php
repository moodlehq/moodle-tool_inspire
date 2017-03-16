<?php

$string['pluginname'] = 'PHP predictor';
$string['errorcantloadmodel'] = 'Model file {$a} does not exist, ensure the model has been trained before using it to predict.';
$string['errornotenoughdata'] = 'The evaluation results varied too much, we need more samples to check if this model is valid. Model deviation = {$a->deviation}, maximum accepted deviation = {$a->accepteddeviation}';
$string['errorlowscore'] = 'The evaluated model prediction accuracy is not very good. Model score = {$a->score}, minimum score = {$a->minscore}';
