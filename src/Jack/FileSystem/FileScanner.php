<?php
namespace Jack\FileSystem;

use Jack\Console\System\Executor;

class FileScanner {
	/** @var  Array */
	private $config;

	/** @var  callable */
	private $logger;

	/**
	 * @param Array $config
	 * @param callable $logger
	 */
	public function __construct($config, $logger) {
		$this->config = $config;
		$this->logger = $logger;
	}

    /**
     * @param $path
     * @param $listFile
     * @param $regex
     */
    public function createFilesList($path, $listFile, $regex = null) {
        $executor = new Executor();
        $executor->execute("find", [
            $path,
            "-type f",
            ($regex ? '-regextype posix-awk -regex "' . $regex . '"': null),
            ">> " . $listFile
        ]);
    }

    public function createChecksumList($listFile, $checksumFile) {
        $executor = new Executor();
        $executor->execute("md5deep", [
            "-f $listFile",
            ">> " . $checksumFile
        ]);
    }


	/**
	 *
	 */
	public function updateFileList() {

	}

	/**
	 * Updates database with files found on file system
	 * and removes obsolete files from database
	 * @return boolean
	 */
	public function updateFileListOld() {
		die("deprecated!");
		//create list of files
		$tmpFileName = realpath($this->config["temporary_path"]) . "/" . md5("Temporary File - " . microtime());
		foreach($this->config["paths"] as $path) {
			$CMD = 'find "'.$path.'" -type f >> ' . $tmpFileName;
			exec($CMD);
		}

		//create file reader
		$tmpFile = new FileReader($tmpFileName);
		$tmpFile->open();

		//update database with files in paths
		$this->output->writeln("Updating files database #1(add)...");
		$this->db->beginTransaction();
		while(($filePath = $tmpFile->readLine())) {
			$this->db->addFile($filePath);
		}
		$this->db->commitTransaction();

		//update database by removing obsolete files
		$this->output->writeln("Updating files database #2(del)...");
		$this->db->beginTransaction();
		$reset = true;
		while(($file = $this->db->getNextFile($reset))) {
			$reset = false;
			$exists = $tmpFile->hasLine($file["file_path"]);
			//$this->output->writeln("Chk(".($exists?"Y":"N")."): " . $file["file_path"]);
			if(!$exists) {
				$this->db->removeFile($file["file_path"]);
			}
		}
		$this->db->commitTransaction();

		//close file reader and remove temporary file
		$tmpFile->close();
		$this->fs->remove($tmpFile->getPath());

		$this->output->writeln("Files in database: " . $this->db->getCount());
		return true;
	}

	/**
	 * @param string $msg
	 */
	private function log($msg) {
		call_user_func($this->logger, $msg);
	}
}