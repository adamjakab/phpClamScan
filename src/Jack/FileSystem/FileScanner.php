<?php
namespace Jack\FileSystem;

use Jack\Console\System\Executor;


class FileScanner {
    /**
     * @param $path
     * @param $listFile
     * @param $regex
     */
    public static function createFilesList($path, $listFile, $regex = null) {
        $executor = new Executor();
        $executor->execute("find", [
            $path,
            "-type f",
            ($regex ? '-regextype posix-awk -regex "' . $regex . '"': null),
            ">> " . $listFile
        ]);
    }

    public static function createChecksumList($listFile, $checksumFile) {
        $executor = new Executor();
        $executor->execute("md5deep", [
            "-f $listFile",
            ">> " . $checksumFile
        ]);
    }

}