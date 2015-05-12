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
	 * @return bool|mixed
	 */
	public function getFile($filePath) {
		$answer = false;
		$query = "SELECT * FROM files WHERE file_path_md5 = :file_path_md5";
		$stmt = $this->db->prepare($query);
		$stmt->bindParam(':file_path_md5', md5($filePath), \PDO::PARAM_STR);
		if($stmt->execute()) {
			$answer = $stmt->fetch(\PDO::FETCH_ASSOC);
			if(!is_array($answer) || $answer["file_path"] != $filePath) {
				$answer = false;
			}
		}
		return $answer;
	}

	/**
	 * @param string $filePath
	 * @return bool
	 */
	public function addFile($filePath) {
		$file = $this->getFile($filePath);
		if(!$file) {
			try {
				$query = "INSERT INTO files (file_path_md5, file_path, file_md5, file_check) VALUES (:file_path_md5, :file_path, :file_md5, :file_check)";
				$stmt = $this->db->prepare($query);
				$stmt->bindParam(':file_path_md5', md5($filePath));
				$stmt->bindParam(':file_path', $filePath);
				$stmt->bindParam(':file_md5', md5_file($filePath));
				$file_check = 0;//unchecked
				$stmt->bindParam(':file_check', $file_check);
				$stmt->execute();
				return true;
			} catch (\PDOException $e) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return mixed
	 */
	public function getCount() {
		$stmt = $this->db->query('SELECT COUNT(*) AS count FROM files');
		$res = $stmt->fetch();
		return ((int)$res["count"]);
	}

	protected function setupDatabase() {
		$this->db->exec("CREATE TABLE IF NOT EXISTS files ("
						. "file_path_md5 TEXT PRIMARY KEY,"
		                . "file_path TEXT,"
						. "file_md5 TEXT,"
		                . "file_check INTEGER"
						. ")"
		);
	}
}