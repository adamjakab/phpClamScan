<?php
namespace Jack\FileSystem;

use Jack\Database\FilesDb;
use Symfony\Component\Console\Output\OutputInterface;

class Scanner {
	/** @var  Array */
	private $config;

	/** @var  FilesDb */
	private $db;

	/** @var  OutputInterface */
	private $output;

	public function __construct($config, OutputInterface $output) {
		$this->config = $config;
		$this->output = $output;
		$this->db = new FilesDb($this->config["database"]);
		$this->db->open();
	}


	/**
	 * @return bool|string
	 */
	public function updateFileList() {
		if(isset($this->config["paths"]) && is_array($this->config["paths"]) && count($this->config["paths"])) {
			$this->output->writeln("Scanning for files...");
			foreach($this->config["paths"] as $path) {
				$this->parseFolder($path);
			}
			$this->output->writeln("Files to scan: " . $this->db->getCount());
			return true;
		} else {
			$this->output->writeln("<error>No usable scan paths were found!</error>");
			return false;
		}
	}

	/**
	 * @param string $dir
	 */
	private function parseFolder($dir) {
		if($this->_checkDir($dir)) {
			$checkList = glob($dir."/*");
			if(count($checkList)) {
				foreach ($checkList as $checkFile) {
					if (is_dir($checkFile)) {
						$this->parseFolder($checkFile);
					} else {
						if(($realpath = realpath($checkFile))) {
							$this->output->write("adding file: " . $realpath);
							$this->db->addFile($realpath);
							$this->output->writeln(" - OK");
						}
					}
				}
			}
		}
	}

	private function _checkDir($dir) {
		return(file_exists($dir) && is_dir($dir));
	}
}