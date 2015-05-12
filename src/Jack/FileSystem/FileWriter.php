<?php
namespace Jack\FileSystem;


class FileWriter {
	/** @var  string */
	private $path;

	/** @var  resource */
	private $fileResource;

	/** @var  integer */
	private $lines;


	public function __construct($path) {
		$this->path = $path;
	}

	public function open() {
		$this->fileResource = fopen($this->path, "w+");
		$this->lines = 0;
	}

	public function close() {
		fclose($this->fileResource);
		$this->fileResource = null;
	}

	public function writeLn($line) {
		if($this->fileResource) {
			fwrite($this->fileResource, $line . "\n");
			$this->lines++;
		}
	}

	public function getLines() {
		return $this->lines;
	}

}