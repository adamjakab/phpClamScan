<?php
namespace Jack\Console\Command;

use Jack\FileSystem\FileReader;
use Jack\FileSystem\FileWriter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Jack\Console\System\Executor;

class MonitorCommand extends Command
{
	/** @var array */
	private $result;

	/**
	 * @param string $name
	 */
	public function __construct($name = null) {
		parent::__construct($name);
		$this->result = [
			"lineCount" => 0,
			"unmatchedCount" => 0,
			"unique" => [
				"scripts" => [],
				"unmatched" => []
			],
		    "scanResult" => []
		];
	}

	/**
	 * Configure command
	 */
	protected function configure() {
		$this->setName("monitor")->setDescription("Monitor php mail log file")->setDefinition(
				[
					new InputArgument('config_file', InputArgument::REQUIRED, 'The yaml(.yml) configuration file'),
				]
			);
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		parent::_execute($input, $output);
		$this->checkConfiguration();
		$fileToCheck = $this->copyLogFile(true);
		$this->parseLogFile($fileToCheck);
		$this->scanMailerScripts();
		if (count($this->result["unique"]["scripts"])) {
			$this->log(print_r($this->result, true));
		}
		$this->log("Done");
	}

	/**
	 * Creates temporary file with scripts to be passed to clamscan
	 */
	protected function scanMailerScripts() {
		if (count($this->result["unique"]["scripts"])) {
			$tmpFile = $this->getTemporaryFileName();
			$fw = new FileWriter($tmpFile);
			$fw->open("w");
			foreach ($this->result["unique"]["scripts"] as $script) {
				$fw->writeLn($script);
			}
			$fw->close();
			//
			$this->log("Scanning " . count($this->result["unique"]["scripts"]) . " files...");
			$executor = new Executor();
			$res = $executor->execute("clamscan", [
				"--infected",
			    "--no-summary",
				"--remove=yes",
				"--stdout",
			    "-f " . $tmpFile
			]);
			$this->result["scanResult"] =  $res["output"];
			$fs = new Filesystem();
			$fs->remove($tmpFile);

		} else {
			$this->log("No files to scan.");
		}
	}

	/**
	 * @param string $tmpFile
	 */
	protected function parseLogFile($tmpFile) {
		$fr = new FileReader($tmpFile);
		$fr->open();
		while ($logLine = $fr->readLine()) {
			$this->parseLogLine($logLine);
		}
		$fs = new Filesystem();
		$fs->remove($tmpFile);
	}

	/**
	 * line ex.:
	 *  mail() on [/home/smilelab.mekit.it/httpdocs/modules/dashboard/inc.php:2]: To: hammadahmed92@yahoo.com -- Headers: ...
	 *
	 * @param string $logLine
	 */
	protected function parseLogLine($logLine) {
		$this->result["lineCount"]++;
		$logLine = substr($logLine, 0, 512);
		$pattern = '#^mail()[^[]*\[(.*):[0-9]*\]:#i';
		preg_match($pattern, $logLine, $matches);
		if ($matches) {
			$scriptPath = trim($matches[2]);
			$this->addUnique("scripts", $scriptPath);
			//$this->log("[" . $this->result["lineCount"] . "]: " . $scriptPath);
		} else {
			$this->result["unmatchedCount"]++;
			$this->addUnique("unmatched", $logLine);
		}
	}

	/**
	 * @param string $targetKey
	 * @param string $value
	 */
	protected function addUnique($targetKey, $value) {
		if (!in_array($value, $this->result["unique"][ $targetKey ])) {
			$this->result["unique"][ $targetKey ][] = $value;
		}
	}

	/**
	 * @param boolean $cleanOriginal
	 * @return string
	 */
	protected function copyLogFile($cleanOriginal = false) {
		$fs = new Filesystem();
		$monitorFileName = $this->config["phpmail"]["monitor"];
		$tmpFileName = $this->getTemporaryFileName();
		$fs->copy($monitorFileName, $tmpFileName);
		if ($cleanOriginal) {
			$fw = new FileWriter($monitorFileName);
			$fw->open("w");
			$fw->writeLn("--- Last check: " . date('Y-m-d H:i:s') . " ---");
			$fw->close();
		}

		return $tmpFileName;
	}

	/**
	 * Checks configuration values used by this command
	 *
	 * @throws \LogicException
	 */
	protected function checkConfiguration() {
		if (!isset($this->config["phpmail"]["monitor"])) {
			throw new \LogicException("Missing 'phpmail'::'monitor' configuration!");
		}
	}
}