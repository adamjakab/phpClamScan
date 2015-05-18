<?php
/**
 * Created by PhpStorm.
 * User: jackisback
 * Date: 18/05/15
 * Time: 22.20
 */

namespace Jack\Database;


class QuaranteneDb extends SqliteDb
{
    /**
     * @param string $dbPath
     * @param callable $logger
     */
    public function __construct($dbPath, $logger) {
        parent::__construct($dbPath, $logger);
        $this->setupDatabase();
    }

    /**
     * @param string $filePath
     * @param string $infection
     * @return bool
     */
    public function registerFile($filePath, $infection){
        $file = $this->getFile($filePath);
        try {
            if(!$file) {
                $query = "INSERT INTO quarantene (file_path, file_content, file_md5, infection, quarantente_time) VALUES"
                    ." (:file_path, :file_content, :file_md5, :infection, :quarantente_time)";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':file_path', $filePath);
                $stmt->bindParam(':file_content', $this->getCryptedFileContent($filePath));
                $stmt->bindParam(':file_md5', md5_file($filePath));
                $stmt->bindParam(':infection', $infection);
                $quarantente_time = time();
                $stmt->bindParam(':quarantente_time', $quarantente_time);
                $stmt->execute();
            }
        } catch (\PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * @param string $filePath
     * @return bool|mixed
     */
    public function getFile($filePath) {
        $answer = false;
        $query = "SELECT * FROM quarantene WHERE file_path = :file_path AND file_md5 = :file_md5";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':file_path', $filePath, \PDO::PARAM_STR);
        $stmt->bindParam(':file_md5', md5_file($filePath), \PDO::PARAM_STR);
        if($stmt->execute()) {
            $answer = $stmt->fetch(\PDO::FETCH_ASSOC);
            if(!is_array($answer)) {
                $answer = false;
            }
        }
        return $answer;
    }

    /**
     * @todo: FIXME!!!
     * @param $filePath
     * @return string
     */
    protected function getCryptedFileContent($filePath) {
        return md5_file($filePath);
    }


    protected function setupDatabase() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS quarantene ("
            . "id INTEGER PRIMARY KEY AUTOINCREMENT,"
            . "file_path TEXT NOT NULL,"
            . "file_content TEXT,"
            . "file_md5 TEXT,"
            . "infection TEXT,"
            . "quarantente_time INTEGER"
            . ")"
        );
        $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS PK on quarantene (id ASC)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS INFKT on quarantene (infection ASC)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS QRTM on quarantene (quarantente_time ASC)");
    }
}