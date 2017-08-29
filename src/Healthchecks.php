<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use prgTW\HealthchecksBundle\IO\Check;
use prgTW\HealthchecksBundle\IO\Checks;
use JMS\Serializer\SerializerInterface;
use prgTW\HealthchecksBundle\Resolver\ResolverInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Healthchecks
{
	const AUTH_HEADER = 'X-Api-Key';

	/** @var HttpClient */
	protected $client;

	/** @var MessageFactory */
	private $messageFactory;

	/** @var string[] */
	protected $apiKeys;

	/** @var int */
	private $baseUri;

	/** @var ResolverInterface */
	private $resolver;

	/** @var array */
	protected $checks = null;

	/** @var SerializerInterface */
	protected $serializer;

	public function __construct(array $apiKeys, string $baseUri, ResolverInterface $resolver, SerializerInterface $serializer)
	{
		$this->client         = HttpClientDiscovery::find();
		$this->messageFactory = MessageFactoryDiscovery::find();
		$this->apiKeys        = $apiKeys;
		$this->baseUri        = $baseUri;
		$this->serializer     = $serializer;
		$this->resolver       = $resolver;
	}

	/**
	 * @return Checks[]
	 */
	public function listAllChecks(): array
	{
		$checksPerClient = array_map(
			function (string $clientName) {
				return $this->listChecks($clientName);
			},
			array_keys($this->apiKeys)
		);

		return $checksPerClient;
	}

	public function listChecks(string $clientName): Checks
	{
		$this->validateClientName($clientName);

		$request = $this->messageFactory->createRequest(
			'get',
			sprintf('%s/api/v1/checks', $this->baseUri),
			[
				self::AUTH_HEADER => $this->apiKeys[$clientName],
			]
		);

		$response = $this->client->sendRequest($request);
		$body     = (string)$response->getBody();

		/** @var Checks $checks */
		$checks = $this->serializer->deserialize($body, Checks::class, 'json');

		return $checks;
	}

	/**
	 * @param string[] $checkNames
	 *
	 * @return Check[]
	 */
	public function setupMany(array $checkNames): array
	{
		array_walk($checkNames, [$this, 'validateCheckName']);

		$requests = [];
		foreach ($checkNames as $checkName)
		{
			$check = $this->getCheck($checkName);
			$json  = [
				'name'   => $check['name'],
				'tags'   => implode(' ', $check['tags']),
				'grace'  => $check['grace'],
				'unique' => $check['unique'],
			];
			if (null !== $check['channels'])
			{
				$json['channels'] = $check['channels'];
			}
			if (null !== $check['timeout'])
			{
				$json['timeout'] = $check['timeout'];
			}
			else
			{
				$json['schedule'] = $check['schedule'];
				$json['tz']       = $check['timezone'];
			}

			$requests[$checkName] = $this->messageFactory->createRequest(
				'post',
				sprintf('%s/api/v1/checks/', $this->baseUri),
				[
					self::AUTH_HEADER => $this->apiKeys[$check['client']],
				],
				json_encode($json, JSON_UNESCAPED_UNICODE)
			);
		}

		$checks    = [];
		$responses = $this->processRequests($requests);
		foreach ($responses as $checkName => $response)
		{
			$body = (string)$response->getBody();

			/** @var Check $check */
			$check = $this->serializer->deserialize($body, Check::class, 'json');

			$checks[$checkName] = $check;
		}

		return $checks;
	}

	/**
	 * @param string[] $checkNames
	 */
	public function pingMany(array $checkNames)
	{
		$checks = $this->setupMany($checkNames);

		$requests = [];
		foreach ($checks as $checkName => $check)
		{
			$pingUrl = $check->getPingUrl();
			$request = $this->messageFactory->createRequest('post', $pingUrl, [
				self::AUTH_HEADER => $this->apiKeys[$this->getCheck($checkName)['client']],
			]);

			$requests[$checkName] = $request;
		}

		$this->processRequests($requests);
	}

	/**
	 * @param string[] $checkNames
	 */
	public function pauseMany(array $checkNames)
	{
		$checks = $this->setupMany($checkNames);

		$requests = [];
		foreach ($checks as $checkName => $check)
		{
			$pauseUrl = $check->getPauseUrl();
			$request  = $this->messageFactory->createRequest('post', $pauseUrl, [
				self::AUTH_HEADER => $this->apiKeys[$this->getCheck($checkName)['client']],
			]);

			$requests[$checkName] = $request;
		}

		$this->processRequests($requests);
	}

	public function setup(string $checkName): Check
	{
		$checks = $this->setupMany([$checkName]);
		$check  = reset($checks);

		return $check;
	}

	public function ping(string $checkName)
	{
		$this->pingMany([$checkName]);
	}

	public function pause(string $checkName)
	{
		$this->pauseMany([$checkName]);
	}

	/**
	 * @param string[] $checkNames
	 *
	 * @return array[]
	 */
	protected function getChecksByClients(array $checkNames): array
	{
		$checks          = array_intersect_key($this->getChecks(), array_flip($checkNames));
		$checksByClients = $this->groupChecksByClient($checks);

		return $checksByClients;
	}

	/**
	 * @param array[] $checks
	 *
	 * @return array[]
	 */
	protected function groupChecksByClient(array $checks): array
	{
		$grouped = [];
		foreach ($checks as $checkName => $check)
		{
			$client = $check['client'];
			if (false === isset($grouped[$client]))
			{
				$grouped[$client] = [];
			}

			$grouped[$client][$checkName] = $check;
		}

		return $grouped;
	}

	/**
	 * @param RequestInterface[] $requests
	 *
	 * @return ResponseInterface[]
	 */
	protected function processRequests(array $requests): array
	{
		$responses = [];
		foreach ($requests as $checkName => $request)
		{
			$responses[$checkName] = $this->client->sendRequest($request);
		}

		return $responses;
	}

	protected function validateClientName(string $clientName)
	{
		if (false === isset($this->apiKeys[$clientName]))
		{
			throw new \InvalidArgumentException(sprintf('Unknown client: %s', $clientName));
		}
	}

	protected function validateCheckName(string $checkName)
	{
		if (null === $this->getCheck($checkName))
		{
			throw new \InvalidArgumentException(sprintf('Unknown check: %s', $checkName));
		}
	}

	private function getChecks(): array
	{
		if(null === $this->checks)
		{
			$this->checks = $this->resolver->resolve();
		}

		return $this->checks;
	}

	/**
	 * @param string $key
	 *
	 * @return array|null
	 */
	private function getCheck(string $key)
	{
		return $this->getChecks()[$key] ?? null;
	}
}
