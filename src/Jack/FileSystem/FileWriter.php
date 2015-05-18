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

	/**
	 * @param string $mode
	 */
	public function open($mode='w+') {
		$this->fileResource = fopen($this->path, $mode);
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

	public function getLinesCount() {
		return $this->lines;
	}

    public function getPath() {
        return $this->path;
    }

}