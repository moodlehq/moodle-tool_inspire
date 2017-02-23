# moodle-tool_inspire

# Installation

https://docs.moodle.org/32/en/Installing_plugins#Installing_a_plugin

# Usage

To start getting predictions you first have to evaluate the model and once you are happy with its predictions accuracy you have to train it.

## From CLI

    // Evaluate the model using this site' contents.
    php cli/evaluate_model.php --modelid=1 --non-interactive

    // Enable the model (you can replace quarters for any other splitting method).
    php admin/tool/inspire/cli/enable_model.php --modelid=1 --timesplitting=\"\\tool_inspire\\local\\time_splitting\\quarters\"

    // These are the 2 tasks that runs through cron, you need to force their execution.
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\train_models
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\predict_models

# View predictions

Note that you will first need to enable and train the model.

As a manager/admin you can access the model predictions by:
- Going to **Site administration > Reports > Inspire models**
- Selecting a context from the predictions list menu

As a user with tool_inspire:listinsights you will receive notifications when new predictions are available to you.
