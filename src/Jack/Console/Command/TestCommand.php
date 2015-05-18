<?php
namespace Jack\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
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
		$this->setName("test")->setDescription("Run some tests")->setDefinition(
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
		$this->doit();
		$this->log("Done");
	}

	private function doit() {
		//
	}

	/**
	 * Checks configuration values used by this command
	 *
	 * @throws \LogicException
	 */
	protected function checkConfiguration() {
		//
	}
}