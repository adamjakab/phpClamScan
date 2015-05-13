<?php
namespace Jack\FileSystem;

class FileReader {
	/** @var  string */
	private $path;

	/** @var  resource */
	private $fileResource;


	public function __construct($path) {
		$this->path = $path;
	}

	public function open() {
		$this->fileResource = fopen($this->path, "r");
	}

	public function close() {
		fclose($this->fileResource);
		$this->fileResource = null;
	}

    public function rewind() {
        rewind($this->fileResource);
    }

	public function hasLine($needle) {
		$CMD = 'grep -xc "' . $needle . '" ' . $this->getPath();
		exec($CMD, $RES, $RV);
		return ($RV==0 && is_array($RES) && isset($RES[0]) && $RES[0]==1);
	}

	/**
	 * @return bool|string
	 */
	public function readLine() {
		$line = false;
		if($this->fileResource && !feof($this->fileResource)) {
			$line = trim(fgets($this->fileResource));
		}
		return $line;
	}

	/**
	 * @return string
	 */
    public function getPath() {
        return $this->path;
    }
}