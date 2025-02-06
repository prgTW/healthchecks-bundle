<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Command;

use prgTW\HealthchecksBundle\Healthchecks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PingCommand extends Command
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
		$this->setName('healthchecks:ping');
		$this->addArgument('check', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Check name(s)');
		$this->setDescription('Ping check(s)');
	}

	/** {@inheritdoc} */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$checks = $input->getArgument('check');
		$this->api->pingMany($checks);

		return 0;
	}
}
