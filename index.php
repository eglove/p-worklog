<?php
declare(strict_types=1);

require ('Helper_Jira.php');

$jiraJson = json_decode(file_get_contents('https://pastebin.com/raw/ciLaHmWs'), true);
Helper_Jira::logWorklog($jiraJson);