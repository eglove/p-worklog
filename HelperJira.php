<?php
declare(strict_types=1);

require ('Worklog.php');

/**
 * Class Jira_Helper
 */
class HelperJira
{
    /**
     * @param array $jiraJson
     * @return Worklog
     */
    public static function logWorklog(array $jiraJson): Worklog
    {
        $worklog = new Worklog($jiraJson);

        if ($worklog->newWorklog === true) {
            $worklog->startWorklog();
        } else {
            $worklog->stopWorklog();
        }

        return $worklog;
    }
}