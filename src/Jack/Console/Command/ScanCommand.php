<?php
namespace Jack\Console\Command;

use Jack\Console\System\Executor;
use Jack\Database\FilesDb;
use Jack\Database\QuaranteneDb;
use Jack\FileSystem\FileScanner;
use Jack\FileSystem\VirusScanner;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ScanCommand extends Command
{
	/** @var  Array */
	private $infections;

	/** @var  FileScanner */
	private $fileScanner;

    /** @var  VirusScanner */
    private $virusScanner;

	/** @var  FilesDb */
	private $filesDb;

    /** @var  QuaranteneDb */
    private $quaranteneDb;

	/**
	 * @param string $name
	 */
	public function __construct($name = null) {
		parent::__construct($name);
		$this->infections = [];

	}

	/**
	 * Configure command
	 */
	protected function configure() {
		$this->setName("scan")->setDescription("Scan a folder for bad stuff")->setDefinition(
				[
					new InputArgument('config_file', InputArgument::REQUIRED, 'The yaml(.yml) configuration file'),
				]
			)->setHelp("Scan a folder for bad stuff");
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		parent::_execute($input, $output);
		$this->checkConfiguration();
		$this->filesDb = new FilesDb($this->config["file_database"], function($msg) {$this->log($msg);});
        $this->quaranteneDb = new QuaranteneDb($this->config["virus_database"], function($msg) {$this->log($msg);});
        $this->fileScanner = new FileScanner($this->config, function($msg) {$this->log($msg);});
        $this->virusScanner = new VirusScanner($this->config, $this->filesDb, $this->quaranteneDb, function($msg) {$this->log($msg);});
		$fs = new FileSystem();
		//
		$filesListFs = $this->generateFilesList();
        $filesListDb = $this->getDatabaseFilesList(false);
        $diffList1 = $this->generateDiffList($filesListDb, $filesListFs);
        $diffList2 = $this->generateDiffList($filesListFs, $filesListDb);
        //
        $this->virusScanner->scan($diffList1);//new files


		//$filesChecksumList = $this->calculateChecksumFromFilesList($filesList);
		//$dbChecksumList = $this->getDatabaseFilesList();
		//$diffList1 = $this->generateDiffList($filesChecksumList, $dbChecksumList);//new or changed
		//$diffList2 = $this->generateDiffList($dbChecksumList, $filesChecksumList);//deleted or changed

		//$this->scanner->scan();
		//$this->sendInfectionReport();

		//$fs->remove($filesListFs);
        //$fs->remove($filesListDb);
		//$fs->remove($checksumList);
		//$fs->remove($dbChecksumList);
		//$fs->remove($diffList1);
		//$fs->remove($diffList2);
		$this->log("Done");
	}

	private function generateDiffList($list1, $list2) {
		$diffListPath = $this->getTemporaryFileName();
		$executor = new Executor();
		$executor->execute("grep", [
			"-Fxvf",
			$list1,
			$list2,
		    "> " . $diffListPath
		]);
		return $diffListPath;
	}

	/**
     * @param boolean $withChecksum
	 * @return string
	 */
	private function getDatabaseFilesList($withChecksum=false) {
		$filesPath = $this->getTemporaryFileName();
		$this->filesDb->dumpFilesList($filesPath, $withChecksum);
		return $filesPath;
	}

	/**
	 * @param $listFilePath
	 * @return string
	 */
	private function calculateChecksumFromFilesList($listFilePath) {
		$checksumFilePath = $this->getTemporaryFileName();
		$this->fileScanner->createChecksumList($listFilePath, $checksumFilePath);
		return $checksumFilePath;
	}

	/**
	 * @return string
	 */
	private function generateFilesList() {
		$listFilePath = $this->getTemporaryFileName();
		$pattern = '^.*\.(php|gif)$';
		foreach ($this->config["paths"] as $path) {
			$this->fileScanner->createFilesList($path, $listFilePath);
		}
		return $listFilePath;
	}




	private function handleInfectedfiles($scanResults) {
		if (is_array($scanResults) && ($viruscount = count($scanResults))) {
			$this->output->writeln("Found viruses: " . $viruscount);
		} else {
			$this->output->writeln("No viruses found.");

			return;
		}

		foreach ($scanResults as &$scanResult) {
			$scanResult = $this->elaborateScanResult($scanResult);
			$scanResult["action"] = strtoupper($this->config["infection_action"]);
			if (!$scanResult["file"]) {
				$scanResult["result"] = "File has disappeared!";
			} else {
				switch ($scanResult["action"]) {
					case "NONE":
						$scanResult["result"] = "No action taken.";
						break;
					case "QUARANTENE":
						$scanResult["result"] = "Moved to quarantene.";
						break;
					case "DELETE":
						$scanResult["result"] = "Deleted.";
						break;
					default:
						$scanResult["result"] = "Unknown action! No action taken.";
						break;
				}
			}
		}
		print_r($scanResults);
		$this->infections = array_merge($this->infections, $scanResults);
		$this->output->writeln(
			"Executed action on all infected files: " . strtoupper($this->config["infection_action"])
		);


		/*
		$this->output->writeln("File($file): <error>Infected[$infectionName]</error>"
		                       .($this->config["move_to_quarantene"] ? " - Quarantened" : ""));

		$this->infections[] = "File($file): Infected[$infectionName]";

		if($this->config["move_to_quarantene"]) {
			$PI = pathinfo($file);
			$quaranteneFolder = $this->config["quarantente_path"] . $PI["dirname"];
			if(!$this->_checkDir($quaranteneFolder)) {
				mkdir($quaranteneFolder, 0700, true);
			}
			$quarantenePath = $quaranteneFolder . '/' . $PI["basename"];
			if(!rename($file, $quarantenePath)) {
				$this->output->writeln("Unable to move infected file to: " . $quarantenePath);
				return;
			}
			//chown($quarantenePath, "root");
			//chgrp($quarantenePath, "root");
			//chmod($quarantenePath, 0);
		}
		*/
	}

	/**
	 * @param string $scanResult
	 * @return array
	 */
	private function elaborateScanResult($scanResult) {
		$answer = [
			"file" => null,
			"infection" => null
		];
		$tmp = explode(":", $scanResult);
		$answer["infection"] = trim(str_replace("FOUND", "", array_pop($tmp)));
		$answer["file"] = realpath(trim(implode((count($tmp) > 1 ? ":" : ""), $tmp)));

		return $answer;
	}

	private function sendInfectionReport() {
		if (!$this->config["report"]["enabled"] || !count($this->infections)) {
			return;
		}

		$body = "The following infections were found on the scanned node.\n" . "\n\n" . print_r(
				$this->infections, true
			);

		$msg = \Swift_Message::newInstance()->setSubject($this->config["report"]["subject"])->setFrom(
				$this->config["report"]["from"]
			)->setTo($this->config["report"]["to"])->setBody($body);

		$trans = \Swift_MailTransport::newInstance();

		$mailer = \Swift_Mailer::newInstance($trans);

		$mailer->send($msg);
	}

	/**
	 * Checks configuration values used by this command
	 *
	 * @throws \LogicException
	 */
	protected function checkConfiguration() {
		if (!isset($this->config["paths"]) || !is_array($this->config["paths"]) || count($this->config["paths"]) < 1) {
			throw new \LogicException("Missing or bad 'paths' configuration!");
		}
	}
}