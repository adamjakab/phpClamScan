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
        $answer = false;
        $this->rewind();
        while(($line = $this->readLine())) {
            if($line == $needle) {
                $answer = true;
                break;
            }
        }
        return $answer;
    }

	public function readLine() {
		$line = false;
		if($this->fileResource && !feof($this->fileResource)) {
			$line = trim(fgets($this->fileResource));
		}
		return $line;
	}

    public function getPath() {
        return $this->path;
    }
}