<?php
declare(strict_types=1);

require('HelperJira.php');

$jiraJson = json_decode(file_get_contents('https://pastebin.com/raw/ciLaHmWs'), true);
HelperJira::logWorklog($jiraJson);