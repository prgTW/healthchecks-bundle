<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use prgTW\HealthchecksBundle\Command\WrapCommand;
use prgTW\HealthchecksBundle\Healthchecks;
use Symfony\Component\Console\Tester\CommandTester;

class WrapCommandTest extends TestCase
{
	public function testPingsCheckWhenWrappedCommandSucceeds(): void
	{
		$api = $this->createMock(Healthchecks::class);
		$api->expects(self::once())->method('ping')->with('simple');

		$tester   = new CommandTester(new WrapCommand($api));
		$exitCode = $tester->execute(['check' => 'simple', 'cmd' => 'echo wrapped-output']);

		self::assertSame(0, $exitCode);
		self::assertStringContainsString('wrapped-output', $tester->getDisplay());
	}

	public function testDoesNotPingCheckWhenWrappedCommandFails(): void
	{
		$api = $this->createMock(Healthchecks::class);
		$api->expects(self::never())->method('ping');

		$tester = new CommandTester(new WrapCommand($api));
		$tester->execute(['check' => 'simple', 'cmd' => 'exit 3']);
	}

	public function testReturnsExitCodeOfWrappedCommand(): void
	{
		$api = $this->createMock(Healthchecks::class);

		$tester   = new CommandTester(new WrapCommand($api));
		$exitCode = $tester->execute(['check' => 'simple', 'cmd' => 'exit 3']);

		self::assertSame(3, $exitCode);
	}

	public function testSendsFailPingWhenWrappedCommandFails(): void
	{
		$api = $this->createMock(Healthchecks::class);
		$api->expects(self::once())->method('fail')->with('simple');

		$tester = new CommandTester(new WrapCommand($api));
		$tester->execute(['check' => 'simple', 'cmd' => 'exit 3']);
	}

	public function testDoesNotSendFailPingWhenWrappedCommandSucceeds(): void
	{
		$api = $this->createMock(Healthchecks::class);
		$api->expects(self::never())->method('fail');

		$tester = new CommandTester(new WrapCommand($api));
		$tester->execute(['check' => 'simple', 'cmd' => 'true']);
	}
}
