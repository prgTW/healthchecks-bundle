<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Command;

use prgTW\HealthchecksBundle\Healthchecks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DoCommand extends Command
{
	const SUPPORTED_OPS = ['ping', 'start', 'pause', 'fail'];

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
		$this->setName('healthchecks:do');
		$this->addArgument('op', InputArgument::REQUIRED, implode(' | ', self::SUPPORTED_OPS));
		$this->addArgument('check', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Check name(s)');
		$this->setDescription('Perform operation on check(s)');
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		$op = $input->getArgument('op');
		if (false === in_array($op, self::SUPPORTED_OPS, true))
		{
			throw new \InvalidArgumentException(vsprintf('"op" argument must be one of [%s]. %s given.', [
				implode(' | ', self::SUPPORTED_OPS),
				var_export($op, true),
			]));
		}
	}

	/** {@inheritdoc} */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$op     = $input->getArgument('op');
		$checks = $input->getArgument('check');
		switch ($op)
		{
			case 'ping':
				$this->api->pingMany($checks);
				break;

			case 'start':
				$this->api->startMany($checks);
				break;

			case 'pause':
				$this->api->pauseMany($checks);
				break;

			case 'fail':
				$this->api->failMany($checks);
				break;
		}
	}
}
