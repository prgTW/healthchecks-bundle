<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Tests\Fixtures;

use JMS\SerializerBundle\JMSSerializerBundle;
use prgTW\HealthchecksBundle\HealthchecksBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
	const BASE_URI = 'https://hc.example.test';
	const API_KEY  = 'test-api-key';

	/** @var string */
	private $bundleAlias;

	/** @var string */
	private $tmpDir;

	public function __construct(string $bundleAlias = 'healthchecks')
	{
		$this->bundleAlias = $bundleAlias;
		$this->tmpDir      = sys_get_temp_dir() . '/healthchecks-bundle-tests/' . uniqid($bundleAlias, true);

		parent::__construct('test', true);
	}

	public function registerBundles(): iterable
	{
		return [
			new FrameworkBundle(),
			new JMSSerializerBundle(),
			new HealthchecksBundle($this->bundleAlias),
		];
	}

	public function registerContainerConfiguration(LoaderInterface $loader): void
	{
		$loader->load(function (ContainerBuilder $container) {
			$container->loadFromExtension('framework', [
				'secret'      => 'test',
				'test'        => true,
				// IO\Check and IO\Checks carry JMS serializer docblock annotations
				'annotations' => ['enabled' => true],
			]);
			$container->loadFromExtension($this->bundleAlias, [
				'api'    => [
					'clients'  => ['example' => self::API_KEY],
					'base_uri' => self::BASE_URI,
				],
				'checks' => [
					'simple' => [
						'client'  => 'example',
						'name'    => 'Simple check',
						'timeout' => 3600,
						'tags'    => ['simple'],
					],
				],
			]);
		});
	}

	public function getProjectDir(): string
	{
		return __DIR__;
	}

	public function getCacheDir(): string
	{
		return $this->tmpDir . '/cache';
	}

	public function getLogDir(): string
	{
		return $this->tmpDir . '/log';
	}
}
