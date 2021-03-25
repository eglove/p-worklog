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
    public const PEER_REVIEW = 'Peer Review';
    public const DEVELOPMENT = 'Development';
    public const IN_PROGRESS = 'In Progress';
    public const DEV_TESTING = 'Dev Testing';
    public const IN_PR = 'In PR';
    public const IN_QA = 'In QA';
    public const START_TIME_STATUSES = [
        self::DEVELOPMENT,
        self::IN_PROGRESS,
        self::DEV_TESTING,
        self::IN_PR,
        self::IN_QA
    ];
    public const WORKFLOW_STATUSES = ['Coding', 'Peer Review', 'QA'];
    public const QA = 'QA';
    public const OPEN = 'Open';
    public const ON_HOLD = 'On Hold';
    public const NEEDS_PR = 'Needs PR';
    public const NEEDS_QA = 'Needs QA';
    public const READY_FOR_QA = 'Ready for QA';
    public const NEEDS_DEPLOYMENT = 'Needs Deployment';
    public const HAS_CONFLICT = 'Has Conflict';
    public const DEPLOYED = 'Deployed';
    public const COMPLETED = 'Completed';
    public const FAILED = 'Failed';
    public const BACKLOG = 'Backlog';
    public const DONE = 'Done';
    public const END_TIME_STATUSES = [
        self::OPEN,
        self::ON_HOLD,
        self::NEEDS_PR,
        self::QA,
        self::NEEDS_QA,
        self::READY_FOR_QA,
        self::NEEDS_DEPLOYMENT,
        self::DONE,
        self::BACKLOG,
        self::HAS_CONFLICT,
        self::DEPLOYED,
        self::COMPLETED,
        self::FAILED
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
        if (PrologueWorklog::getUnstoppedWorklog($this->issueKey)) {
            return false;
        }

        return true;
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
                case self::DEV_TESTING:
                case self::IN_PROGRESS:
                    $assignee = $this->assigneeId;
                    break;
                case self::IN_PR:
                    $assignee = $this->prAssigneeId;
                    break;
                case self::IN_QA:
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
                case self::DEV_TESTING:
                case self::IN_PROGRESS:
                    $category = self::DEVELOPMENT;
                    break;
                case self::IN_PR:
                    $category = self::PEER_REVIEW;
                    break;
                case self::IN_QA:
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
        $this->prologueWorklog->save();
    }

    public function stopWorklog(): void
    {
        $this->prologueWorklog->stop();
        $this->thirdPartyWorklog->save();
    }
}