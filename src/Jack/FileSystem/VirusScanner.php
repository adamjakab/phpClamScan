<?php
namespace Jack\FileSystem;

use Jack\Console\System\Executor;
use Jack\Database\FilesDb;
use Jack\Database\QuaranteneDb;
use Jack\FileSystem\FileReader;
use Jack\FileSystem\FileWriter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class VirusScanner {
	/** @var  Array */
	private $config;

    /** @var  callable */
    private $logger;

	/** @var  FilesDb */
	private $filesDb;

    /** @var  QuaranteneDb */
    private $quaranteneDb;

    /**
     * @param array $config
     * @param FilesDb $filesDb
     * @param QuaranteneDb $quarantenteDb
     * @param callable $logger
     */
	public function __construct($config, $filesDb, $quarantenteDb, $logger) {
		$this->config = $config;
        $this->filesDb = $filesDb;
        $this->quaranteneDb = $quarantenteDb;
		$this->logger = $logger;
	}


    /**
     * @param string $listFilePath
     */
    public function scan($listFilePath) {
        if (!$listFilePath) {
            $this->log("No files to scan!");
            return;
        }
        $this->log("Scanning...");
        $infections = [];
        $infectedFiles = [];

        //Default Virus Scan
        $executor = new Executor();
        $res = $executor->execute("clamscan", [
            "--infected",
            "--no-summary",
            "-f " . $listFilePath
        ]);
        if ($res["return_val"] != 0 && ($viruscount = count($res["output"]))) {
            $infections = array_merge($infections, $res["output"]);
        } else {
            $this->log("No infections found.");
        }

        //HANDLE INFECTIONS
        $this->log("Number of infections found: " . count($infections));
        $this->quaranteneDb->beginTransaction();
        foreach($infections as &$infection) {
            $infection = $this->elaborateScanResult($infection);
            $infection["action"] = strtoupper($this->config["infection_action"]);

            $this->quaranteneDb->registerFile($infection["file_path"], $infection["infection"]);
            $this->log("Infected(".$infection["file_path"].")[".$infection["infection"]."] - action: " . $infection["action"]);

            if(!$infection["file_path"]) {
                $infection["result"] = "File has disappeared!";
            } else {
                $infectedFiles[] = $infection["file_path"];
                switch($infection["action"]) {
                    case "NONE":
                        $infection["result"] = "No action taken.";
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
        $this->quaranteneDb->commitTransaction();

        //HANDLE NOT INFECTED FILES
        $tmpFile = new FileReader($listFilePath);
        $tmpFile->open();
        $this->filesDb->beginTransaction();
        while(($filePath = $tmpFile->readLine())) {
            if(!in_array($filePath, $infectedFiles)) {
                $this->filesDb->updateFile($filePath, "OK");
            }
        }
        $this->filesDb->commitTransaction();



        $this->log(print_r($infections, true));
    }

    /**
     * @param string $scanResult
     * @return array
     */
    private function elaborateScanResult($scanResult) {
        $answer = ["file_path"=>null, "infection"=>null];
        $tmp = explode(":", $scanResult);
        $answer["infection"] = trim(str_replace("FOUND","",array_pop($tmp)));
        $answer["file_path"] = trim(implode((count($tmp)>1?":":""), $tmp));
        return $answer;
    }

    /**
     * @param string $msg
     */
    private function log($msg) {
        call_user_func($this->logger, $msg);
    }
}