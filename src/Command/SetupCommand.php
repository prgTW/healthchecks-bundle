<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use prgTW\HealthchecksBundle\Healthchecks;

class SetupCommand extends Command
{
	/** @var Healthchecks */
	protected $api;

	/** @var string[] */
	protected $availableChecks;

	public function __construct(Healthchecks $api, array $availableChecks)
	{
		parent::__construct();
		$this->api             = $api;
		$this->availableChecks = $availableChecks;
	}

	/** {@inheritdoc} */
	protected function configure()
	{
		parent::configure();
		$this->setName('healthchecks:setup');
		$this->addArgument('check', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Check name(s)');
		$this->setDescription('Setup checks configuration');
	}

	/** {@inheritdoc} */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$checks = $input->getArgument('check') ?: $this->availableChecks;
		$this->api->setupMany($checks);
	}
}
