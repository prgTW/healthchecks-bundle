<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Tests\Command;

use GuzzleHttp\Psr7\Response;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Strategy\MockClientStrategy;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use prgTW\HealthchecksBundle\Healthchecks;
use prgTW\HealthchecksBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CommandsTest extends TestCase
{
	const PING_URL = TestKernel::BASE_URI . '/ping/00000000-0000-0000-0000-000000000000';

	/** @var TestKernel */
	private $kernel;

	/** @var Client */
	private $httpClient;

	protected function setUp(): void
	{
		HttpClientDiscovery::prependStrategy(MockClientStrategy::class);

		$this->kernel = new TestKernel();
		$this->kernel->boot();

		$this->httpClient = new Client();

		/** @var Healthchecks $api */
		$api = $this->kernel->getContainer()->get('test.service_container')->get('healthchecks.api');
		$api->setClient($this->httpClient);
	}

	protected function tearDown(): void
	{
		$this->kernel->shutdown();
	}

	/**
	 * @dataProvider pingCommandProvider
	 */
	public function testCommandSetsUpCheckAndPingsIt(string $commandName, array $arguments, string $expectedPingUrl): void
	{
		$this->httpClient->addResponse(new Response(201, [], json_encode([
			'name'       => 'Simple check',
			'tags'       => 'simple',
			'grace'      => 3600,
			'n_pings'    => 0,
			'status'     => 'new',
			'last_ping'  => null,
			'next_ping'  => null,
			'timeout'    => 3600,
			'ping_url'   => self::PING_URL,
			'pause_url'  => TestKernel::BASE_URI . '/api/v1/checks/00000000-0000-0000-0000-000000000000/pause',
			'update_url' => TestKernel::BASE_URI . '/api/v1/checks/00000000-0000-0000-0000-000000000000',
		])));
		$this->httpClient->addResponse(new Response(200, [], 'OK'));

		$application = new Application($this->kernel);
		$tester      = new CommandTester($application->find($commandName));
		$exitCode    = $tester->execute($arguments);

		self::assertSame(0, $exitCode);

		$requests = $this->httpClient->getRequests();
		self::assertCount(2, $requests);

		$setupRequest = $requests[0];
		self::assertSame('POST', strtoupper($setupRequest->getMethod()));
		self::assertSame(TestKernel::BASE_URI . '/api/v1/checks/', (string)$setupRequest->getUri());
		self::assertSame(TestKernel::API_KEY, $setupRequest->getHeaderLine(Healthchecks::AUTH_HEADER));
		$setupBody = json_decode((string)$setupRequest->getBody(), true);
		self::assertSame('Simple check', $setupBody['name']);
		self::assertSame('simple', $setupBody['tags']);
		self::assertSame(3600, $setupBody['timeout']);

		$pingRequest = $requests[1];
		self::assertSame('POST', strtoupper($pingRequest->getMethod()));
		self::assertSame($expectedPingUrl, (string)$pingRequest->getUri());
		self::assertSame(TestKernel::API_KEY, $pingRequest->getHeaderLine(Healthchecks::AUTH_HEADER));
	}

	public function pingCommandProvider(): array
	{
		return [
			'healthchecks:ping'    => ['healthchecks:ping', ['check' => ['simple']], self::PING_URL],
			'healthchecks:do ping' => ['healthchecks:do', ['op' => 'ping', 'check' => ['simple']], self::PING_URL],
			'healthchecks:do fail' => ['healthchecks:do', ['op' => 'fail', 'check' => ['simple']], self::PING_URL . '/fail'],
		];
	}
}
