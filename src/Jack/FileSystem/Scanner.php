<?php
namespace Jack\FileSystem;

use Jack\Database\FilesDb;
use Jack\FileSystem\FileReader;
use Jack\FileSystem\FileWriter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Scanner {
	/** @var  Array */
	private $config;

	/** @var  FilesDb */
	private $db;

	/** @var  OutputInterface */
	private $output;

    /** @var Filesystem */
    private $fs;

	public function __construct($config, OutputInterface $output) {
		$this->config = $config;
		$this->output = $output;
		$this->db = new FilesDb($this->config["file_database"]);
		$this->db->open();
        $this->fs = new Filesystem();
	}

    public function scan() {
        //create file writer to list files to be scanned
        $tmpFileName = realpath($this->config["temporary_path"]) . "/" . md5("Temporary File - " . microtime());
        $tmpFile = new FileWriter($tmpFileName);
        $tmpFile->open();

        $reset = true;
        while(($file = $this->db->getNextFile($reset, "UNCHECKED"))) {
            $reset = false;
            $needsScan = $this->checkIfFileNeedsToBeScanned($file);
            //$this->output->writeln("Checking file[".($needsScan?"Y":"N")."]: " . $file["file_path"]);
            if($needsScan) {
                $tmpFile->writeLn($file["file_path"]);
            }
        }
        $tmpFile->close();

        $fileCount = $tmpFile->getLinesCount();
        $this->output->writeln("Files to be scanned: " . $fileCount);
        if($fileCount == 0) {
            $this->output->writeln("Nothing to be scanned.");
            return;
        }

        //Default Virus Scan
        $infections = [];
        $CMD = $this->getClamscanPath()
            . " --infected"
            . " --no-summary"
            . " -f " . $tmpFile->getPath();
        //$this->output->writeln("Scanning($CMD)...");
        exec($CMD, $SCANRES, $RV);
        if($RV != 0 && ($viruscount = count($SCANRES)) ) {
            $infections = array_merge($infections, $SCANRES);
        }
        $infectionsCount = count($infections);
        if($infectionsCount == 0) {
            $this->output->writeln("No infections found.");
            return;
        }

        $this->output->writeln("Number of infections found: " . $infectionsCount);
        foreach($infections as &$infection) {
            $infection = $this->elaborateScanResult($infection);
            $infection["action"] = strtoupper($this->config["infection_action"]);
            if(!$infection["file_path"]) {
                $infection["result"] = "File has disappeared!";
            } else {
                switch($infection["action"]) {
                    case "NONE":
                        $infection["result"] = "No action taken.";
                        break;
                    case "QUARANTENE":
                        $infection["result"] = "Moved to quarantene.";
                        break;
                    case "DELETE":
                        $infection["result"] = "Deleted.";
                        break;
                    default:
                        $infection["result"] = "Unknown action! No action taken.";
                        break;
                }
            }
        }
        print_r($infections);
    }

    /**
     * @param string $scanResult
     * @return array
     */
    private function elaborateScanResult($scanResult) {
        $answer = ["file_path"=>null, "infection"=>null];
        $tmp = explode(":", $scanResult);
        $answer["infection"] = trim(str_replace("FOUND","",array_pop($tmp)));
        $answer["file_path"] = realpath(trim(implode((count($tmp)>1?":":""), $tmp)));
        return $answer;
    }

    /**
     * @throws \Exception
     * @return string
     */
    protected function getClamscanPath() {
        $CMD = "which clamscan";
        exec($CMD, $RES, $RV);
        if($RV==0 && is_array($RES) && isset($RES[0])) {
            return $RES[0];
        } else {
            throw new \Exception("Clamscan binary not found!");
        }
    }

    /**
     * @param Array $file
     * @return bool
     */
    protected function checkIfFileNeedsToBeScanned($file) {
        $answer = false;
        if($file["file_status"] == "UNCHECKED") {
            $answer = true;
        } else if((int)$file["check_time"] == 0) {
            $answer = true;
        }
        return $answer;
    }

	/**
	 * Updates database with files found on file system
     * and removes obsolete files from database
     * @return boolean
	 */
	public function updateFileList() {
        if(!isset($this->config["paths"]) || !is_array($this->config["paths"]) || !count($this->config["paths"])) {
            $this->output->writeln("<error>No usable scan paths were found!</error>");
            return false;
        }

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
        $this->db->beginTransaction();
        while(($filePath = $tmpFile->readLine())) {
            $this->db->addFile($filePath);
        }
        $this->db->commitTransaction();

        //update database by removing obsolete files
        $this->db->beginTransaction();
        $reset = true;
        while(($file = $this->db->getNextFile($reset))) {
            $reset = false;
            $exists = $tmpFile->hasLine($file["file_path"]);
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
}