<?php
declare(strict_types=1);

require('PrologueWorklog.php');
require('JiraWorklog.php');

/**
 * Interface IWorklog
 */
interface IWorklog {
    public function save();
}

/**
 * Class Worklog
 */
class Worklog
{
    public const UNASSIGNED = 'Unassigned';
    public const NO_CATEGORY = 'No Category';

    // Start Statuses
    const DEV_TESTING = 'Dev Testing';
    const IN_PR = 'In PR';
    const IN_QA = 'In QA';
    const IN_PROGRESS = 'In Progress';
    // Stop Statuses
    const BACKLOG = 'Backlog';
    const COMPLETED = 'Completed';
    const DEPLOYED = 'Deployed';
    const DONE = 'Done';
    const FAILED = 'Failed';
    const HAS_CONFLICT = 'Has Conflict';
    const NEEDS_DEPLOYMENT = 'Needs Deployment';
    const NEEDS_PR = 'Needs PR';
    const NEEDS_QA = 'Needs QA';
    const READY_FOR_QA = 'Ready for QA';
    const ON_HOLD = 'On Hold';
    const OPEN = 'Open';
    // Other Statuses
    const CODING = 'Coding';
    const DEVELOPMENT = 'Development';
    const PEER_REVIEW = 'Peer Review';
    const QA = 'QA';

    const WORKFLOW_STATUSES = [self::CODING, self::DEVELOPMENT, self::PEER_REVIEW, self::QA];

    const DEVELOPMENT_STATUSES = [self::CODING, self::DEV_TESTING, self::DEVELOPMENT, self::IN_PROGRESS];
    const PR_STATUSES = [self::IN_PR, self::PEER_REVIEW];
    const QA_STAUSES = [self::IN_QA, self::QA];

    const START_TIME_STATUSES = [
        self::CODING,
        self::DEV_TESTING,
        self::DEVELOPMENT,
        self::IN_PR,
        self::IN_PROGRESS,
        self::IN_QA,
        self::PEER_REVIEW,
        self::QA
    ];
    const END_TIME_STATUSES = [
        self::BACKLOG,
        self::COMPLETED,
        self::DEPLOYED,
        self::DONE,
        self::FAILED,
        self::HAS_CONFLICT,
        self::NEEDS_DEPLOYMENT,
        self::NEEDS_PR,
        self::NEEDS_QA,
        self::READY_FOR_QA,
        self::ON_HOLD,
        self::OPEN,
    ];

    // @param PrologueWorklog
    public $prologueWorklog;
    // @param JiraWorklog
    public $thirdPartyWorklog;
    public $newWorklog;
    public $jiraJson;
    private $issueStatus;
    private $issueKey;
    private $assigneeId;
    private $prAssigneeId;
    private $qaAssigneeId;

    public function __construct(array $jiraJson)
    {
        $this->jiraJson = $jiraJson;
        $this->issueStatus = $jiraJson['fields']['status']['name'];
        $this->issueKey = $jiraJson['key'];
        $this->assigneeId = $jiraJson['fields']['assignee']['accountId'];
        $this->prAssigneeId = $jiraJson['fields']['customfield_11619']['accountId'];
        $this->qaAssigneeId = $jiraJson['fields']['customfield_11625']['accountId'];

        if ($this->isNewWorklog() && $this->isStartStatus()) {
            $this->prologueWorklog = new PrologueWorklog(
                new DateTime('now'),
                $this->getAssigneeId(),
                $this->issueStatus,
                false,
                $this->issueKey,
                $this->getIssueCategory()
            );
        } elseif (!$this->isNewWorklog() && $this->isEndStatus()) {
            $this->prologueWorklog = PrologueWorklog::getUnstoppedWorklog($this->issueKey);

            $timeDiff = (new DateTime('now'))->getTimestamp() - $this->prologueWorklog->started_at->getTimestamp();
            $this->thirdPartyWorklog = new JiraWorklog(
                $this->getAssigneeId(),
                $this->issueKey,
                $timeDiff,
                $this->prologueWorklog->started_at->format('c') . '.000-0600',
                $this->prologueWorklog->category
            );
        }
    }

    private function isNewWorklog(): bool
    {
        $this->newWorklog = true;

        if (PrologueWorklog::getUnstoppedWorklog($this->issueKey)) {
            $this->newWorklog = false;
        }

        return $this->newWorklog;
    }

    private function isStartStatus(): bool
    {
        return in_array($this->issueStatus, self::START_TIME_STATUSES, true);
    }

    private function getAssigneeId(): string
    {
        $assignee = self::UNASSIGNED;

        if ($this->isEndStatus()) {
            $assignee = $this->prologueWorklog->jira_account_id;
        }

        if ($this->isStartStatus()) {
            switch ($this->issueStatus) {
                case in_array($this->issueStatus, self::DEVELOPMENT_STATUSES, true):
                    $assignee = $this->assigneeId;
                    break;
                case in_array($this->issueStatus, self::PR_STATUSES, true):
                    $assignee = $this->prAssigneeId;
                    break;
                case in_array($this->issueStatus, self::QA_STAUSES, true):
                    $assignee = $this->qaAssigneeId;
                    break;
                default:
                    $assignee = self::UNASSIGNED;
            }
        }

        return $assignee ?? self::UNASSIGNED;
    }

    private function isEndStatus(): bool
    {
        return in_array($this->issueStatus, self::END_TIME_STATUSES, true);
    }

    private function getIssueCategory(): string
    {
        $category = self::NO_CATEGORY;

        if ($this->isEndStatus()) {
            $category = $this->prologueWorklog->category;
        }

        if ($this->isStartStatus()) {
            switch ($this->issueStatus) {
                case in_array($this->issueStatus, self::DEVELOPMENT_STATUSES, true):
                    $category = self::DEVELOPMENT;
                    break;
                case in_array($this->issueStatus, self::PR_STATUSES, true):
                    $category = self::PEER_REVIEW;
                    break;
                case in_array($this->issueStatus, self::QA_STAUSES, true):
                    $category = self::QA;
                    break;
                default:
                    $category = self::NO_CATEGORY;
            }
        }

        return $category ?? self::NO_CATEGORY;
    }

    public function startWorklog(): void
    {
        $this->prologueWorklog->create();
    }

    public function stopWorklog(): void
    {
        $this->prologueWorklog->started_at = new DateTime('now');
        $this->thirdPartyWorklog->secondsSpentOnLog =
            $this->prologueWorklog->started_at - new DateTime('now');

        if ($this->thirdPartyWorklog->secondsSpentOnLog < 60 ||
            $this->thirdPartyWorklog->secondsSpentOnLog > 28800) {
            // Email
        } else {
            $this->thirdPartyWorklog->save();
        }

        $this->prologueWorklog->save();
    }
}