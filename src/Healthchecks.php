<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle;

use GuzzleHttp\Psr7\Utils;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use JMS\Serializer\SerializerInterface;
use prgTW\HealthchecksBundle\IO\Check;
use prgTW\HealthchecksBundle\IO\Checks;
use prgTW\HealthchecksBundle\Resolver\ResolverInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function GuzzleHttp\Psr7\stream_for;

class Healthchecks
{
	const AUTH_HEADER = 'X-Api-Key';

	/** @var HttpClient */
	protected $client;

	/** @var RequestFactoryInterface */
	private $requestFactory;

	/** @var UriFactoryInterface */
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
		$this->requestFactory  = Psr17FactoryDiscovery::findRequestFactory();
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

		$request = $this
			->requestFactory
			->createRequest('get', $uri)
			->withHeader(self::AUTH_HEADER, $this->apiKeys[$clientName]);

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

			$requests[$checkName] = $this
				->requestFactory
				->createRequest('post', sprintf('%s/api/v1/checks/', $this->baseUri))
				->withHeader(self::AUTH_HEADER, $this->apiKeys[$check['client']])
				->withBody(Utils::streamFor(json_encode($json, JSON_UNESCAPED_UNICODE)));
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
			$request = $this
				->requestFactory
				->createRequest('post', $pingUrl)
				->withHeader(self::AUTH_HEADER, $this->apiKeys[$this->getCheck($checkName)['client']]);

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
			$request  = $this
				->requestFactory
				->createRequest('post', $pauseUrl)
				->withHeader(self::AUTH_HEADER, $this->apiKeys[$this->getCheck($checkName)['client']]);

			$requests[$checkName] = $request;
		}

		$this->processRequests($requests);
	}

	/**
	 * @param string[] $checkNames
	 */
	public function startMany(array $checkNames)
	{
		$checks = $this->setupMany($checkNames);

		$requests = [];
		foreach ($checks as $checkName => $check)
		{
			$pingUrl = $check->getPingUrl() . '/start';
			$request = $this
				->requestFactory
				->createRequest('post', $pingUrl)
				->withHeader(self::AUTH_HEADER, $this->apiKeys[$this->getCheck($checkName)['client']]);

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

	public function start(string $checkName)
	{
		$this->startMany([$checkName]);
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

	private function getUriFactory(): UriFactoryInterface
	{
		if (null === $this->uriFactory)
		{
			$this->uriFactory = Psr17FactoryDiscovery::findUriFactory();
		}

		return $this->uriFactory;
	}
}
