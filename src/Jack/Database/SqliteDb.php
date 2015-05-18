<?php
namespace Jack\Database;

class SqliteDb {
	/** @var  string */
	protected $dbPath;

	/** @var  \PDO */
	protected $db;

	/** @var  callable */
	private $logger;

	/**
	 * @param string $dbPath
	 * @param callable $logger
	 */
	public function __construct($dbPath, $logger) {
		$this->dbPath = $dbPath;
		$this->logger = $logger;
		$this->db = new \PDO('sqlite:' . $this->dbPath);
		$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function commitTransaction() {
        $this->db->commit();
    }

	/**
	 * @param string $msg
	 */
	private function log($msg) {
		call_user_func($this->logger, $msg);
	}
}