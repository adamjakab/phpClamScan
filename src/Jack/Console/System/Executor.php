<?php
/**
 * Created by PhpStorm.
 * User: jackisback
 * Date: 13/05/15
 * Time: 23.01
 */

namespace Jack\Console\System;

use Symfony\Component\Filesystem\Filesystem;

class Executor {
    /** @var  Filesystem */
    private $fs;

    public function __construct() {
        $this->fs = new Filesystem();
    }

    /**
     * @param $binary
     * @param array $arguments
     * @return array
     * @throws \Exception
     */
    public function execute($binary, $arguments=[]) {
        $binary = $this->getBinaryPath($binary);
        $arguments = implode(" ", array_filter($arguments));
        $command = "$binary $arguments";
        echo "Executing command: " . $command . "\n";
        exec($command, $output, $return_val);
        return [
            "output" => $output,
            "return_val" => $return_val
        ];
    }

    /**
     * @param $binary
     * @return mixed
     * @throws \Exception
     */
    protected function getBinaryPath($binary) {
        if(!$this->fs->exists($binary)) {
            $CMD = "which $binary";
            exec($CMD, $RES, $RV);
            if($RV==0 && is_array($RES) && isset($RES[0])) {
                return $RES[0];
            } else {
                throw new \Exception("Binary($binary) not found!");
            }
        }
        return $binary;
    }
}