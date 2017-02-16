# moodle-tool_inspire

    // Evaluate the model using this site' contents.
    php cli/evaluate_model.php --modelid=1 --non-interactive

    // Enable the model (you can replace quarters for any other splitting method).
    php admin/tool/inspire/cli/enable_model.php --modelid=1 --timesplitting=\"\\tool_inspire\\local\\time_splitting\\quarters\"

    // These are the 2 tasks that runs through cron, you need to force their execution.
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\train_models
    php admin/tool/task/cli/schedule_task.php --execute=\\tool_inspire\\task\\predict_models
