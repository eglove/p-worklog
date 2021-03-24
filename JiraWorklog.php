<?php
declare(strict_types=1);


/**
 * Class JiraWorklog
 */
class JiraWorklog implements IWorklog
{
    public $userId;
    public $key;
    public $secondsSpentOnLog;
    public $startTime;
    public $category;

    /**
     * JiraWorklog constructor.
     * @param $userId
     * @param $key
     * @param $secondsSpentOnLog
     * @param $startTime
     * @param $category
     */
    public function __construct(string $userId, string $key, int $secondsSpentOnLog, string $startTime, string $category)
    {
        $this->userId = $userId;
        $this->key = $key;
        $this->secondsSpentOnLog = $secondsSpentOnLog;
        $this->startTime = $startTime;
        $this->category = $category;
    }

    public function save(): void
    {
        $comment = $this->category . ' logged to ' . $this->key;
        $body = json_encode([
            'timeSpentSeconds' => $this->secondsSpentOnLog,
            'comment' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'text' => $comment,
                                'type' => 'text'
                            ]
                        ]
                    ]
                ]
            ],
            'started' => $this->startTime,
            'author' => [
                'accountId' => $this->userId,
            ]
        ]);

        // Post to Jira
    }
}