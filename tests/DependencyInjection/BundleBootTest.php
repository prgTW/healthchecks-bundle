<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use prgTW\HealthchecksBundle\DependencyInjection\HealthchecksExtension;
use prgTW\HealthchecksBundle\Healthchecks;
use prgTW\HealthchecksBundle\HealthchecksBundle;
use prgTW\HealthchecksBundle\Resolver\DefaultResolver;
use prgTW\HealthchecksBundle\Tests\Fixtures\TestKernel;

class BundleBootTest extends TestCase
{
	public function testContainerExtensionUsesBundleAlias(): void
	{
		$extension = (new HealthchecksBundle('custom_alias'))->getContainerExtension();

		self::assertInstanceOf(HealthchecksExtension::class, $extension);
		self::assertSame('custom_alias', $extension->getAlias());
	}

	/**
	 * @dataProvider aliasProvider
	 */
	public function testKernelBootsWithBundle(string $alias): void
	{
		$kernel = new TestKernel($alias);
		$kernel->boot();

		try
		{
			$container = $kernel->getContainer()->get('test.service_container');

			self::assertInstanceOf(Healthchecks::class, $container->get('healthchecks.api'));
			self::assertInstanceOf(DefaultResolver::class, $container->get('healthchecks.resolver'));
			self::assertSame(['simple'], $container->get('healthchecks.resolver')->resolveNames());
			self::assertSame('Europe/Warsaw', $container->getParameter('healthchecks.checks')['simple']['timezone']);
		}
		finally
		{
			$kernel->shutdown();
		}
	}

	public function aliasProvider(): array
	{
		return [
			'default alias' => ['healthchecks'],
			'custom alias'  => ['cron_monitoring'],
		];
	}
}
