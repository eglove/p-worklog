<?php
declare(strict_types=1);

/**
 * Class PrologueWorklog
 */
class PrologueWorklog implements IWorklog
{
    private $mysqli;
    public $started_at;
    public $stopped_at;
    public $jira_account_id;
    public $status;
    public $logged;
    public $jira_key;
    public $category;

    /**
     * PrologueWorklog constructor.
     * @param DateTime $started_at
     * @param string $jira_account_id
     * @param string $status
     * @param bool $logged
     * @param string $jira_key
     * @param string $category
     * @param DateTime|null $stopped_at
     */
    public function __construct(
        DateTime $started_at,
        string $jira_account_id,
        string $status,
        bool $logged,
        string $jira_key,
        string $category,
        DateTime $stopped_at = null
    ) {
        $this->mysqli = new mysqli("localhost", "root", "", "prologue_worklogs");
        $this->started_at = $started_at;
        $this->jira_account_id = $jira_account_id;
        $this->status = $status;
        $this->logged = $logged;
        $this->jira_key = $jira_key;
        $this->category = $category;
        $this->stopped_at = $stopped_at;
    }

    public function save(): void {
        $isLogged = $this->logged === true ? 1 : 0;
        $query = "insert into prologue_worklogs.prologue_worklogs 
            (started_at, jira_account_id, status, logged, jira_key, category, stopped_at)
            values (
                '" . $this->started_at->format(DATE_ATOM) . "',
                '" . $this->jira_account_id . "',
                '" . $this->status . "',
                " . $isLogged . ",
                '" . $this->jira_key . "',
                '" . $this->category . "',
                '" . $this->stopped_at->format(DATE_ATOM) . "',
            )";

        $this->mysqli->query($query);
    }

    public function stop(): void {
        $query = 'update prologue_worklogs.prologue_worklogs SET stopped_at = "' . (new DateTime('now'))->format(DATE_ATOM) . '" where jira_key = "' . $this->jira_key . '" and stopped_at IS NULL';
        $this->mysqli->query($query);
    }

    /**
     * @param string $jiraKey
     * @return false|PrologueWorklog
     * @throws Exception
     */
    public static function getUnstoppedWorklog(string $jiraKey) {
        $mysqli = new mysqli("localhost", "root", "", "prologue_worklogs");
        $result = $mysqli->query("select * from prologue_worklogs.prologue_worklogs where jira_key = '" . $jiraKey . "' and logged = false");
        $result = $result->fetch_array();

        if ($result) {
            return new self(
                new DateTime($result['started_at']),
                $result['jira_account_id'],
                $result['status'],
                (bool) $result['logged'],
                $result['jira_key'],
                $result['category']
            );
        }

        return false;
    }
}