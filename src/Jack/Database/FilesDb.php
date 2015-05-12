<?php
namespace Jack\Database;


class FilesDb {
	/** @var  string */
	private $dbPath;

	/** @var  \PDO */
	private $db;

    /** @var  \PDOStatement */
    private $filesWalker;


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
     * Returns the next row on each call
     * @param boolean $reset
     * @param string|null $status
     * @return bool|mixed
     */
    public function getNextFile($reset=false, $status=null) {
        if(!$this->filesWalker || $reset) {
            $query = "SELECT * FROM files";
            if($status) {
                $query .= " WHERE file_status = :file_status";
            }
            $this->filesWalker = $this->db->prepare($query);
            if($status) {
                $this->filesWalker->bindParam(':file_status', $status, \PDO::PARAM_STR);
            }
            $this->filesWalker->execute();
        }
        $answer = $this->filesWalker->fetch(\PDO::FETCH_ASSOC);
        return is_array($answer) ? $answer : false;
    }

	/**
	 * @param string $filePath
	 * @return bool
	 */
	public function addFile($filePath) {
		$file = $this->getFile($filePath);
		if(!$file) {
			try {
				$query = "INSERT INTO files (file_path_md5, file_path, file_md5, file_status) VALUES"
                    ." (:file_path_md5, :file_path, :file_md5, :file_status)";
				$stmt = $this->db->prepare($query);
				$stmt->bindParam(':file_path_md5', md5($filePath));
				$stmt->bindParam(':file_path', $filePath);
				$stmt->bindParam(':file_md5', md5_file($filePath));
				$file_status = "UNCHECKED";
				$stmt->bindParam(':file_status', $file_status);
				$stmt->execute();
				return true;
			} catch (\PDOException $e) {
				return false;
			}
		}
		return true;
	}

    /**
     * @param string $filePath
     * @return bool
     */
    public function removeFile($filePath) {
        try {
            $query = "DELETE FROM files WHERE file_path_md5 = :file_path_md5";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':file_path_md5', md5($filePath));
            return $stmt->execute();
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function commitTransaction() {
        $this->db->commit();
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
						. "file_path_md5 TEXT NOT NULL,"
		                . "file_path TEXT  NOT NULL,"
						. "file_md5 TEXT,"
		                . "file_status TEXT,"
                        . "check_time INTEGER"
						. ")"
		);
        $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS PK on files (file_path_md5 ASC)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS FST on files (file_status ASC)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS CHT on files (check_time ASC)");
	}
}