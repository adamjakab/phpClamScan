<?php
namespace Jack\Database;


class FilesDb {
	/** @var  string */
	private $dbPath;

	/** @var  \PDO */
	private $db;


	public function __construct($dbPath) {
		$this->dbPath = $dbPath;
	}

	public function open() {
		$this->db = new \PDO('sqlite:' . $this->dbPath);
		$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->setupDatabase();
	}


	/**
	 * @param string $filePath
	 * @param string $fileMd5
	 * @return bool
	 */
	public function addFile($filePath, $fileMd5=null) {
		try {
			$insert = "INSERT INTO files (file_path, file_md5) VALUES (:file_path, :file_md5)";
			$stmt = $this->db->prepare($insert);
			$stmt->bindParam(':file_path', $filePath);
			$stmt->bindParam(':file_md5', $fileMd5);
			$stmt->execute();
			return true;
		} catch (\PDOException $e) {
			return false;
		}
	}

	protected function setupDatabase() {
		$this->db->exec("CREATE TABLE IF NOT EXISTS files (file_path TEXT PRIMARY KEY, file_md5 TEXT)");
	}
}