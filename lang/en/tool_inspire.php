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

$string['accuracy'] = 'Accuracy';
$string['allindicators'] = 'All indicators';
$string['analysablenotused'] = 'Analysable {$a->analysableid} not used: {$a->errors}';
$string['analysablenotvalidfortarget'] = 'Analysable {$a->analysableid} is not valid for this target: {$a->result}';
$string['analysingsitedata'] = 'Analysing the site';
$string['bettercli'] = 'Models\' evaluation and execution are heavy processes, it is better to run them through command line interface';
$string['clienablemodel'] = 'You can enable the model by selecting a time splitting method by its id. Note that you can also enable it later using the web interface (\'none\' to exit)';
$string['coursenotyetstarted'] = 'The course is not yet started';
$string['coursenotyetfinished'] = 'The course is not yet finished';
$string['coursetoolong'] = 'Duration is more than 1 year';
$string['disabledmodel'] = 'Sorry, this model has been disabled by the administrator';
$string['editmodel'] = 'Edit model {$a}';
$string['edittrainedwarning'] = 'This model has already been trained, note that changing its indicators or its time splitting method will delete its previous predictions and start generating the new ones';
$string['enabled'] = 'Enabled';
$string['enabledtimesplittings'] = 'Time splitting methods';
$string['enabledtimesplittings_help'] = 'The time splitting method divides the course duration in parts, the predictions engine will run at the end of these parts. It is recommended that you only enable the time splitting methods you could be interested on using; the evaluation process will iterate through all of them so the more time splitting methods to go through the slower the evaluation process will be.';
$string['erroralreadypredict'] = '{$a} file has already been used to predict';
$string['errorcantenablenotimesplitting'] = 'You need to select a time splitting method before enabling the model';
$string['errordisabledmodel'] = '{$a} model is disabled and can not be used to predict';
$string['errorinvalidindicator'] = 'Invalid {$a} indicator';
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
$string['errorprocessornotready'] = 'The selected predictions processor is not ready: {$a}';
$string['errorsamplenotavailable'] = 'The predicted sample is not available anymore';
$string['info'] = 'Info';
$string['errorunexistingtimesplitting'] = 'The selected time splitting method is not available';
$string['errorunknownaction'] = 'Unknown action';
$string['evaluate'] = 'Evaluate';
$string['evaluatemodel'] = 'Evaluate model';
$string['eventactionclicked'] = 'Prediction action clicked';
$string['evaluationinbatches'] = 'The site contents are calculated and stored in batches, during evaluation you can stop the process at any moment, the next time you run it it will continue from the point you stopped it.';
$string['executemodel'] = 'Execute';
$string['executingmodel'] = 'Training model and calculating predictions';
$string['executionresults'] = 'Results using {$a->name} (id: {$a->id}) course duration splitting';
$string['extrainfo'] = 'Info';
$string['generalerror'] = 'Evaluation error. Status code {$a}';
$string['goodmodel'] = 'This is a good model and it can be used to predict, enable it and execute it to start getting predictions.';
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
$string['indicator:socialbreadthassign'] = 'Assignment activities\' social breadth';
$string['indicator:socialbreadthbook'] = 'Book resources\' social breadth';
$string['indicator:socialbreadthchat'] = 'Chat activities\' social breadth';
$string['indicator:socialbreadthchoice'] = 'Choice activities\' social breadth';
$string['indicator:socialbreadthdata'] = 'Database activities\' social breadth';
$string['indicator:socialbreadthfeedback'] = 'Feedback activities\' social breadth';
$string['indicator:socialbreadthfolder'] = 'Folder resources social breadth';
$string['indicator:socialbreadthforum'] = 'Forum activities\' social breadth';
$string['indicator:socialbreadthglossary'] = 'Glossary activities\' social breadth';
$string['indicator:socialbreadthimscp'] = 'IMS content packages\' social breadth';
$string['indicator:socialbreadthlabel'] = 'Label resources social breadth';
$string['indicator:socialbreadthlesson'] = 'Lesson activities\' social breadth';
$string['indicator:socialbreadthlti'] = 'LTI activities\' social breadth';
$string['indicator:socialbreadthpage'] = 'Page resources\' social breadth';
$string['indicator:socialbreadthquiz'] = 'Quiz activities\' social breadth';
$string['indicator:socialbreadthresource'] = 'File resources\' social breadth';
$string['indicator:socialbreadthscorm'] = 'SCORM activities\' social breadth';
$string['indicator:socialbreadthsurvey'] = 'Survey activities\' social breadth';
$string['indicator:socialbreadthurl'] = 'URL resources\' social breadth';
$string['indicator:socialbreadthwiki'] = 'Wiki activities\' social breadth';
$string['indicator:socialbreadthworkshop'] = 'Workshop activities\' social breadth';
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
$string['invalidanalysablefortimesplitting'] = 'It can not be analysed using {$a} time splitting method';
$string['labelstudentdropoutyes'] = 'Student at risk of dropping out';
$string['labelstudentdropoutno'] = 'Not at risk';
$string['loginfo'] = 'Log extra info';
$string['lowaccuracy'] = 'The model accuracy is low';
$string['messageprovider:insights'] = 'Insights generated by prediction models';
$string['modeloutputdir'] = 'Models output directory';
$string['modeloutputdirinfo'] = 'Directory where prediction processors store all evaluation info. Useful for debugging and research.';
$string['modelslist'] = 'Models list';
$string['modeltimesplitting'] = 'Time splitting';
$string['nocompletiondetection'] = 'No method available to detect course completion (no completion nor competencies nor course grades with numeric values)';
$string['nocourseactivity'] = 'Not enough course activity';
$string['nocourses'] = 'No courses to analyse';
$string['nocoursesections'] = 'No course sections';
$string['nocoursestudents'] = 'No students';
$string['nodata'] = 'No data available';
$string['nodatatoevaluate'] = 'There is no data to evaluate the model';
$string['nodatatopredict'] = 'There is no data to use for predictions';
$string['nodatatotrain'] = 'There is no data to use as training data';
$string['nonewdata'] = 'No new data available';
$string['nonewtimeranges'] = 'No new time ranges, nothing to predict';
$string['nopredictionsyet'] = 'No predictions yet';
$string['notenoughdata'] = 'The site does not contain enough data to evaluate this model';
$string['notdefined'] = 'Not yet defined';
$string['novaliddata'] = 'No valid data available';
$string['prediction'] = 'Prediction';
$string['predictionresults'] = 'Prediction results';
$string['predictions'] = 'Predictions';
$string['predictmodels'] = 'Predict models';
$string['predictorresultsin'] = 'Predictor logged information in {$a} directory';
$string['predictiondetails'] = 'Prediction details';
$string['predictionprocessfinished'] = 'Prediction process finished';
$string['predictionsprocessor'] = 'Predictions processor';
$string['predictionsprocessor_help'] = 'Prediction processors are the machine learning backends that process the datasets generated by calculating models\' indicators and targets.';
$string['pluginname'] = 'Inspire';
$string['modelresults'] = '{$a} results';
$string['nocourses'] = 'No courses to analyse';
$string['skippingcourse'] = 'Skipping course {$a}';
$string['studentroles'] = 'Student roles';
$string['subplugintype_predict'] = 'Predictions processor';
$string['subplugintype_predict_plural'] = 'Predictions processors';
$string['successfullyanalysed'] = 'Successfully analysed';
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
$string['timesplittingmethod'] = 'Time splitting method';
$string['trainingprocessfinished'] = 'Training process finished';
$string['trainingresults'] = 'Training results';
$string['trainmodels'] = 'Train models';
$string['viewlog'] = 'Log';
$string['viewprediction'] = 'View prediction details';
$string['viewpredictions'] = 'View model predictions';
