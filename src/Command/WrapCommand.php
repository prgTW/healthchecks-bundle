<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use prgTW\HealthchecksBundle\Healthchecks;

class WrapCommand extends Command
{
	/** @var Healthchecks */
	protected $api;

	public function __construct(Healthchecks $api)
	{
		parent::__construct();
		$this->api = $api;
	}

	/** {@inheritdoc} */
	protected function configure()
	{
		parent::configure();
		$this->setName('healthchecks:wrap');
		$this->addArgument('check', InputArgument::REQUIRED, 'Check name');
		$this->addArgument('command', InputArgument::REQUIRED, 'Command to run');
		$this->setDescription('Pings given check(s) upon successful execution of a given command');
	}

	/** {@inheritdoc} */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$check   = $input->getArgument('check');
		$command = $input->getArgument('command');

		passthru($command, $exitCode);

		if (0 === $exitCode)
		{
			$this->api->ping($check);
		}
	}
}
