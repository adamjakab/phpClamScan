<?php
/**
 * Created by Adam Jakab.
 * Date: 06/05/15
 * Time: 12.22
 */

namespace Jack\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class ScanCommand extends Command
{
	/** @var  Array */
	private $config;

	/** @var  Array */
	private $infections;

	/** @var  OutputInterface */
	private $output;

	/** @var Filesystem */
	private $fs;

	/**
	 * @param string $name
	 */
	public function __construct($name = null) {
		parent::__construct($name);
		$this->fs = new Filesystem();
	}

	/**
	 * Configure command
	 */
	protected function configure() {
		$this->setName("scan")
		->setDescription("Scan a folder for bad stuff")
		->setDefinition([
							new InputArgument('config_file', InputArgument::REQUIRED, 'The yaml(.yml) configuration file'),
		                ])
		->setHelp("Scan a folder for bad stuff");
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;
		$this->infections = [];

		$config_file = $input->getArgument('config_file');
		$this->parseConfiguration($config_file);

		$listFilePath = $this->buildFileList();
		$this->scan($listFilePath);
		$this->sendInfectionReport();
	}


	/**
	 *
	 * /usr/bin/clamscan -d /usr/local/maldetect/tmp/.runtime.user.21512.hdb -d /usr/local/maldetect/tmp/.runtime.user.21512.ndb  -r --infected --no-summary -f /usr/local/maldetect/tmp/.find.21512
	 *
	 * @param string $listFilePath
	 */
	private function scan($listFilePath) {
		if(!$listFilePath) {
			$this->output->writeln("No files to scan!");
			return;
		}

		//Default Virus Scan
		$CMD = $this->config["clamscan_binary"]
		       . " --infected"
		       . " --no-summary"
		       . " -f " . $listFilePath
			;
		$this->output->writeln("Scanning($CMD)...");
		exec($CMD, $SCANRES_1, $RV);
		if($RV != 0 && ($viruscount = count($SCANRES_1)) ) {
			$this->handleInfectedfiles($SCANRES_1);
		} else {
			$this->output->writeln("Clean");
		}

		//Optional additional database scans


		$this->fs->remove($listFilePath);

	}


	private function handleInfectedfiles($scanResults) {
		if(is_array($scanResults) && ($viruscount = count($scanResults))) {
			$this->output->writeln("Found viruses: " . $viruscount);
		} else {
			return;
		}

		foreach($scanResults as &$scanResult) {
			$scanResult = $this->elaborateScanResult($scanResult);
			$scanResult["action"] = strtoupper($this->config["infection_action"]);
			if(!$scanResult["file"]) {
				$scanResult["result"] = "File has disappeared!";
			} else {
				switch($scanResult["action"]) {
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

		$this->output->writeln("Executed action on all infected files: " . strtoupper($this->config["infection_action"]));


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
		$answer = ["file"=>null, "infection"=>null];
		$tmp = explode(":", $scanResult);
		$answer["infection"] = trim(str_replace("FOUND","",array_pop($tmp)));
		$answer["file"] = realpath(trim(implode((count($tmp)>1?":":""), $tmp)));
		return $answer;
	}

	private function sendInfectionReport() {
		if(!$this->config["report"]["enabled"] || !count($this->infections)) {
			return;
		}

		$body = "The following infections were found on the scanned node.\n"
				. "The identified files were quarantened: " . ($this->config["move_to_quarantene"] ? "YES" : "NO")
		        . "\n\n"
				. implode("\n", $this->infections);

		$msg = \Swift_Message::newInstance()
			->setSubject($this->config["report"]["subject"])
			->setFrom($this->config["report"]["from"])
			->setTo($this->config["report"]["to"])
			->setBody($body)
		;

		$trans = \Swift_MailTransport::newInstance();

		$mailer = \Swift_Mailer::newInstance($trans);

		$mailer->send($msg);
	}

	/**
	 * @return bool|string
	 */
	private function buildFileList() {
		if(isset($this->config["paths"]) && is_array($this->config["paths"]) && count($this->config["paths"])) {
			$fileName = md5("FileList-".microtime()).'.txt';
			$filePath = $this->config["temporary_path"] . $fileName;
			$filesList  = [];
			foreach($this->config["paths"] as $path) {
				$filesList = array_merge($filesList, $this->parseFolder($path));
			}
			$this->fs->dumpFile($filePath, implode("\n", $filesList));
			$this->output->writeln("Files to scan: " . count($filesList));
			return $filePath;
		} else {
			$this->output->writeln("<error>No usable scan paths were found!</error>");
			return false;
		}
	}

	/**
	 * @param string $dir
	 * @return array
	 */
	private function parseFolder($dir) {
		$list = [];
		if($this->_checkDir($dir)) {
			$checkList = glob($dir."/*");
			if(count($checkList)) {
				foreach ($checkList as $checkFile) {
					if (is_dir($checkFile)) {
						$list = array_merge($list, $this->parseFolder($checkFile));
					} else {
						$list[] = realpath($checkFile);
					}
				}
			}
		}
		return $list;
	}

	/**
	 * @param string $config_file
	 */
	private function parseConfiguration($config_file) {
		if(!file_exists($config_file)) {
			throw new \InvalidArgumentException("The configuration file does not exist!");
		}

		$yamlParser = new Parser();
		$config = $yamlParser->parse(file_get_contents($config_file));
		if(!is_array($config) || !isset($config["config"])) {
			throw new \InvalidArgumentException("Malformed configuration file!");
		}
		$this->config = $config["config"];

		//check and create quarantene folder
		if(!$this->_checkDir($this->config["quarantente_path"])) {
			mkdir($this->config["quarantente_path"], 0700, true);
		}
	}

	private function _checkFile($file) {
		return(file_exists($file) && is_file($file));
	}

	private function _checkDir($dir) {
		return(file_exists($dir) && is_dir($dir));
	}
}