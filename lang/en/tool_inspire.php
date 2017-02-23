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
 * Strings for tool_inspire.
 *
 * @package tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['enabled'] = 'Enabled';
$string['enabledtimesplittings'] = 'Time splitting methods';
$string['erroralreadypredict'] = '{$a} file has already been used to predict';
$string['errordisabledmodel'] = '{$a} model is disabled and can not be used to predict';
$string['errorinvalidtimesplitting'] = 'Invalid time splitting, please ensure you added the class fully qualified class name';
$string['errornoenabledmodels'] = 'There are not enabled models to train';
$string['errornoenabledandtrainedmodels'] = 'There are not enabled and trained models to predict';
$string['errornoindicators'] = 'This model does not have any indicator';
$string['errornopredictresults'] = 'No results returned from the predictions processor, check the output directory contents for more info';
$string['errornorunrecord'] = 'Couldn\'t locate the current run record in the database';
$string['errornotimesplittings'] = 'This model does not have any time splitting method';
$string['errornoroles'] = 'Student or teacher roles have not been defined. Define them in inspire plugin settings page.';
$string['errornotarget'] = 'This model does not have any target';
$string['errornottrainedmodel'] = '{$a} model has not been trained yet';
$string['errorpredictionformat'] = 'Wrong prediction calculations format';
$string['errorpredictionnotfound'] = 'Prediction not found';
$string['errorpredictionsprocessor'] = 'Predictions processor error: {$a}';
$string['errorpredictwrongformat'] = 'The predictions processor return can not be decoded: "{$a}"';
$string['errorsamplenotavailable'] = 'The predicted sample is not available anymore';
$string['errorunexistingtimesplitting'] = 'The selected time splitting method is not available';
$string['analysingsitedata'] = 'Analysing the site';
$string['evaluationminscore'] = 'Evaluation minimum score';
$string['indicator:accessesafterend'] = 'Accesses after the end date';
$string['indicator:accessesbeforestart'] = 'Accesses before the start date';
$string['indicator:anywrite'] = 'Any write action';
$string['indicator:cognitivedepthassign'] = 'Assignment activities\' cognitive depth';
$string['indicator:cognitivedepthbook'] = 'Book resources\' cognitive depth';
$string['indicator:cognitivedepthchat'] = 'Chat activities\' cognitive depth';
$string['indicator:cognitivedepthchoice'] = 'Choice activities\' cognitive depth';
$string['indicator:cognitivedepthdata'] = 'Database activities\' cognitive depth';
$string['indicator:cognitivedepthfeedback'] = 'Feedback activities\' cognitive depth';
$string['indicator:cognitivedepthfolder'] = 'Folder resources cognitive depth';
$string['indicator:cognitivedepthforum'] = 'Forum activities\' cognitive depth';
$string['indicator:cognitivedepthglossary'] = 'Glossary activities\' cognitive depth';
$string['indicator:cognitivedepthimscp'] = 'IMS content packages\' cognitive depth';
$string['indicator:cognitivedepthlabel'] = 'Label resources cognitive depth';
$string['indicator:cognitivedepthlesson'] = 'Lesson activities\' cognitive depth';
$string['indicator:cognitivedepthlti'] = 'LTI activities\' cognitive depth';
$string['indicator:cognitivedepthpage'] = 'Page resources\' cognitive depth';
$string['indicator:cognitivedepthquiz'] = 'Quiz activities\' cognitive depth';
$string['indicator:cognitivedepthresource'] = 'File resources\' cognitive depth';
$string['indicator:cognitivedepthscorm'] = 'SCORM activities\' cognitive depth';
$string['indicator:cognitivedepthsurvey'] = 'Survey activities\' cognitive depth';
$string['indicator:cognitivedepthurl'] = 'URL resources\' cognitive depth';
$string['indicator:cognitivedepthwiki'] = 'Wiki activities\' cognitive depth';
$string['indicator:cognitivedepthworkshop'] = 'Workshop activities\' cognitive depth';
$string['indicator:readactions'] = 'Read actions amount';
$string['indicator:completeduserprofile'] = 'User profile is completed';
$string['indicator:userforumstracking'] = 'User is tracking forums';
$string['indicators'] = 'Indicators';
$string['insightmessagesubject'] = 'New insight for "{$a->contextname}": {$a->insightname}';
$string['insightinfo'] = '{$a->insightname} - {$a->contextname}';
$string['insightinfomessage'] = 'There are some insights you may find useful. Check out {$a}';
$string['inspiremodels'] = 'Inspire models';
$string['inspire:listinsights'] = 'List insights';
$string['invalidtimesplitting'] = 'Model with id {$a} needs a time splitting method before it can be used to train';
$string['labelstudentdropoutyes'] = 'Student at risk of dropping out';
$string['labelstudentdropoutno'] = 'Not at risk';
$string['messageprovider:insights'] = 'Insights generated by prediction models';
$string['modeloutputdir'] = 'Models output directory';
$string['modeloutputdirinfo'] = 'Directory where prediction processors store all evaluation info. Useful for debugging and research.';
$string['modelslist'] = 'Models list';
$string['modeltimesplitting'] = 'Time splitting';
$string['nocourses'] = 'No courses to analyse';
$string['notdefined'] = 'Not yet defined';
$string['predictmodels'] = 'Predict models';
$string['predictiondetails'] = 'Prediction details';
$string['predictionsprocessor'] = 'Predictions processor';
$string['pluginname'] = 'Inspire';
$string['skippingcourse'] = 'Skipping course {$a}';
$string['studentroles'] = 'Student roles';
$string['subplugintype_predict'] = 'Predictions processor';
$string['subplugintype_predict_plural'] = 'Predictions processors';
$string['target'] = 'Target';
$string['target:coursedropout'] = 'Students at risk of dropping out';
$string['target:coursedropoutinfo'] = 'Here you can find a list of students at risk of dropping out.';
$string['teacherroles'] = 'Teacher roles';
$string['timemodified'] = 'Last modification';
$string['timesplitting:deciles'] = 'Deciles';
$string['timesplitting:decilesaccum'] = 'Deciles accumulative';
$string['timesplitting:nosplitting'] = 'No time splitting';
$string['timesplitting:quarters'] = 'Quarters';
$string['timesplitting:quartersaccum'] = 'Quarters accumulative';
$string['timesplitting:singlerange'] = 'Single range';
$string['timesplitting:weekly'] = 'Weekly';
$string['timesplitting:weeklyaccum'] = 'Weekly accumulative';
$string['trainandenablemodel'] = 'Select which model you want to enable';
$string['trained'] = 'Trained';
$string['trainingmodel'] = 'Training {$a} model';
$string['trainmodels'] = 'Train models';
$string['viewprediction'] = 'View prediction details';
