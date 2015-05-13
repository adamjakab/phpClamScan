<?php
/**
 * Created by PhpStorm.
 * User: jackisback
 * Date: 13/05/15
 * Time: 21.57
 */

namespace Jack\Console\Command;

use Jack\FileSystem\FileScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Jack\FileSystem\FileReader;
use Jack\FileSystem\FileWriter;
use Jack\FileSystem\Scanner;
use Jack\Database\FilesDb;

class TestCommand extends Command
{
    /** @var  Array */
    private $config;

    /** @var  OutputInterface */
    private $output;

    /**
     * @param string $name
     */
    public function __construct($name = null) {
        parent::__construct($name);
    }

    /**
     * Configure command
     */
    protected function configure() {
        $this->setName("test")
            ->setDescription("Run some tests")
            ->setDefinition([
                new InputArgument('config_file', InputArgument::REQUIRED, 'The yaml(.yml) configuration file'),
            ]);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->output = $output;
        $config_file = $input->getArgument('config_file');
        $this->parseConfiguration($config_file);
        $this->doit();
        $this->output->writeln("Done");
    }

    private function doit() {
        $listFilePath = $this->generateFilesList();
        $this->output->writeln("got files list: " . $listFilePath);
        $checksumFilePath = $this->calculateSha1FromFilesList($listFilePath);
        $this->output->writeln("got checksum list: " . $checksumFilePath);
    }

    /**
     * @param $listFilePath
     * @return string
     */
    private function calculateSha1FromFilesList($listFilePath) {
        $checksumFilePath = realpath($this->config["temporary_path"]) . "/" . md5("ChecksumList:" . microtime());
        FileScanner::createChecksumList($listFilePath, $checksumFilePath);
        return $checksumFilePath;
    }

    /**
     * @return string
     */
    private function generateFilesList() {
        $listFilePath = realpath($this->config["temporary_path"]) . "/" . md5("FileList:" . microtime());
        $pattern = '^.*\.(php|gif)$';
        foreach($this->config["paths"] as $path) {
            FileScanner::createFilesList($path, $listFilePath);
        }
        return $listFilePath;
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
    }
}