# Moodle inspire

This is a work in progress branch with a limited set of indicators. Once this is ready for testing we will make an announcement through the usual media channels. Use it at your own risk.

# Installation

https://docs.moodle.org/32/en/Installing_plugins#Installing_a_plugin

# Configuration

A couple of important settings:

## Predictions processor

Prediction processors are the machine learning backends that process the datasets generated from the calculated indicators and targets. This plugin is shipped with 2 prediction processors:

* The PHP one is the default, there are no other system requirements
* The Python one is more powerful and it generates graphs with the model performance but it requires setting up extra stuff like Python itself (https://wiki.python.org/moin/BeginnersGuide/Download) and the moodleinspire package (Only Python 2.7 support at the moment, working on compatibility with Python 3.x).

<!-- not displayed as a code block under a list unless we add something like this comment -->
    pip install moodleinspire


## Time splitting methods

The time splitting method divides the course duration in parts, the predictions engine will run at the end of these parts. It is recommended that you only enable the time splitting methods you could be interested on using; the evaluation process will iterate through all of them so the more time splitting methods to go through the slower the evaluation process will be.

# Usage

To start getting predictions you first have to evaluate the model and once you are happy with its predictions accuracy you have to train it.

## From CLI

    // Evaluate the model using your site' contents (this is optional but useful as you want to see how the different time splitting methods perform)
    php cli/evaluate_model.php --modelid=1 --non-interactive

    // Enable the model (you can replace quarters for any other splitting method).
    php admin/tool/inspire/cli/enable_model.php --modelid=1 --timesplitting=\"\\tool_inspire\\local\\time_splitting\\quarters\"

    // These are the 2 tasks that will run through cron regularly, you can force their execution.
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\train_models
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\predict_models

## From the web interface

- Go to **Site administration > Reports > Inspire models**
- Select **Evaluate** from the **Actions** drop down menu (this is optional but useful as you want to see how the different time splitting methods perform)
- Select **Edit** from the **Actions** drop down menu, check 'Enabled' checkbox and select the time splitting method you prefer
- Select **Execute** to start getting predictions

# View predictions

Note that you will first need to enable and execute the model.

As a manager/admin you can access the model predictions by:
- Going to **Site administration > Reports > Inspire models**
- Selecting a context from the predictions list menu

User with **tool/inspire:listinsights** capability will receive notifications when new predictions are available for them. e.g. Course teachers will receive a notification about their students at risk of dropping out.
