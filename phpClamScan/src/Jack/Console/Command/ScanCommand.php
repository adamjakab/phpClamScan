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

class ScanCommand extends Command
{
	/** @var  Array */
	private $config;

	/** @var  Array */
	private $infections;

	/** @var  OutputInterface */
	private $output;

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

		if(isset($this->config["paths"]) && is_array($this->config["paths"]) && count($this->config["paths"])) {
			foreach($this->config["paths"] as $path) {
				$this->output->writeln("Scanning folder: <info>".$path."</info>");
				$this->scanDirectory($path);
			}
			$this->sendInfectionReport();
		} else {
			$this->output->writeln("<error>No usable scan paths were found!</error>");
			return false;
		}
		return true;
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

	/**
	 * @param string $dir
	 */
	private function scanDirectory($dir) {
		if(!$this->_checkDir($dir)) {
			$this->output->writeln("Directory($dir) does not exist!");
			return;
		}

		$checkList = glob($dir."/*");
		if(!count($checkList)) {
			return;
		}

		foreach($checkList as $checkFile) {
			if(is_dir($checkFile)) {
				$this->scanDirectory($checkFile);
			} else {
				$this->scanFile($checkFile);
			}
		}
	}

	/**
	 * @param string $file
	 */
	private function scanFile($file) {
		if(!$this->_checkFile($file)) {
			$this->output->writeln("File($file) does not exist!");
			return;
		}

		if(($fileSize=filesize($file)) > $this->config["max_file_size"]) {
			return;
		}

		$CMD = 'cat "' . $file . '" | ' . $this->config["clamscan_binary"] . " " . $this->config["clamscan_options"] . ' -';
		exec($CMD, $RES, $RV);
		if($RV != 0) {
			$this->handleInfectedfile($file, $RES);
		}
	}

	private function handleInfectedfile($file, $scanResult) {
		$infectionName = "Unknown";
		if(is_array($scanResult)&&isset($scanResult[0])) {
			$infectionName = trim(str_replace(["stream:","FOUND"],["",""],$scanResult[0]));

		}

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


	private function _checkFile($file) {
		return(file_exists($file) && is_file($file));
	}

	private function _checkDir($dir) {
		return(file_exists($dir) && is_dir($dir));
	}
}