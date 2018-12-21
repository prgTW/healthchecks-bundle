<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use JMS\Serializer\SerializerInterface;
use prgTW\HealthchecksBundle\IO\Check;
use prgTW\HealthchecksBundle\IO\Checks;
use prgTW\HealthchecksBundle\Resolver\ResolverInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Healthchecks
{
	const AUTH_HEADER = 'X-Api-Key';

	/** @var HttpClient */
	protected $client;

	/** @var MessageFactory */
	private $messageFactory;

	/** @var UriFactory */
	private $uriFactory;

	/** @var string[] */
	protected $apiKeys;

	/** @var int */
	private $baseUri;

	/** @var ResolverInterface */
	private $resolver;

	/** @var OptionsResolver */
	private $optionsResolver;

	/** @var array */
	protected $checks = [];

	/** @var SerializerInterface */
	protected $serializer;

	const DEFAULT_CHECK_VALUES = [
		'timeout'  => null,
		'grace'    => 3600,
		'schedule' => '* * * * *',
		'tags'     => [],
		'channels' => null,
		'unique'   => ['name'],
	];

	const ALLOWED_UNIQUE_VALUES = ['name', 'tags', 'timeout', 'grace'];

	protected $defaultTimezone = null;


	public function __construct(
		array $apiKeys,
		string $baseUri,
		string $defaultTimezone,
		ResolverInterface $resolver,
		SerializerInterface $serializer
	) {
		$this->client          = HttpClientDiscovery::find();
		$this->messageFactory  = MessageFactoryDiscovery::find();
		$this->apiKeys         = $apiKeys;
		$this->baseUri         = $baseUri;
		$this->serializer      = $serializer;
		$this->resolver        = $resolver;
		$this->defaultTimezone = $defaultTimezone;

		$this->configureChecksOptions();
	}

	public function configureChecksOptions()
	{
		$this->optionsResolver = new OptionsResolver();
		$this->optionsResolver->setDefaults(self::DEFAULT_CHECK_VALUES)
			->setDefault('timezone', $this->defaultTimezone)
			->setRequired(['client', 'name'])
			->setAllowedTypes('client', 'string')
			->setAllowedTypes('name', 'string')
			->setAllowedTypes('timeout', ['null', 'int'])
			->setAllowedTypes('grace', ['null', 'int'])
			->setAllowedTypes('unique', ['null', 'array'])
			->setAllowedTypes('tags', ['null', 'array'])
			->setAllowedTypes('schedule', ['null', 'string'])
			->setAllowedTypes('channels', ['null', 'string'])
			->setAllowedTypes('timezone', ['null', 'string'])
			->setAllowedValues('timeout', function ($value) {
				return null === $value || ($value > 59 && $value < 604800);
			})
			->setAllowedValues('grace', function ($value) {
				return $value > 59 && $value < 604800;
			})
			->setAllowedValues('unique', function ($value) {
				return 0 === count(array_diff($value, self::ALLOWED_UNIQUE_VALUES));
			});
	}


	/**
	 * @param string[] $tags To filter checks by their tags
	 *
	 * @return Checks[]
	 */
	public function listAllChecks(array $tags = []): array
	{
		$checksPerClient = array_map(
			function (string $clientName) use ($tags) {
				return $this->listChecks($clientName, $tags);
			},
			array_keys($this->apiKeys)
		);

		return $checksPerClient;
	}

	/**
	 * @param string   $clientName
	 * @param string[] $tags
	 *
	 * @return Checks
	 */
	public function listChecks(string $clientName, array $tags = []): Checks
	{
		$tags = array_map([$this, 'pairTagParam'], $tags);

		$queryString = $this->buildQuery($tags);

		$uri = $this->getUriFactory()
			->createUri(sprintf('%s/api/v1/checks/', $this->baseUri))
			->withQuery($queryString);

		$this->validateClientName($clientName);

		$request = $this->messageFactory->createRequest(
			'get',
			$uri,
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
			$check = $this->optionsResolver->resolve($check);

			$json = [
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
	 * @param bool     $success
	 */
	public function pingMany(array $checkNames, bool $success = true)
	{
		$checks = $this->setupMany($checkNames);

		$requests = [];
		foreach ($checks as $checkName => $check)
		{
			$pingUrl = $check->getPingUrl();
			if (false === $success)
			{
				$pingUrl .= '/fail';
			}
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
	public function failMany(array $checkNames)
	{
		$this->pingMany($checkNames, false);
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

	public function ping(string $checkName, bool $success = true)
	{
		$this->pingMany([$checkName], $success);
	}

	public function fail(string $checkName)
	{
		$this->pingMany([$checkName], false);
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
		$this->checks = $this->resolver->resolve();

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

	/**
	 * @param array[][] $queryParams
	 *
	 * @return string
	 */
	private function buildQuery(array $queryParams = []): string
	{
		$queryParams = array_map(
			function (array $keyValue) {
				list($key, $value) = $keyValue;

				return sprintf('%s=%s', $key, urlencode($value));
			},
			$queryParams
		);

		return implode('&', $queryParams);
	}

	private function pairTagParam(string $tag)
	{
		return ['tag', $tag];
	}

	private function getUriFactory(): UriFactory
	{
		if (null === $this->uriFactory)
		{
			$this->uriFactory = UriFactoryDiscovery::find();
		}

		return $this->uriFactory;
	}
}
