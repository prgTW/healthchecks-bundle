<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\Resolver;

class DefaultResolver implements ResolverInterface
{
	/** @var array */
	private $checks = [];

	public function __construct(array $checks)
	{
		$this->checks = $checks;
	}

	public function resolve(): array
	{
		return $this->checks;
	}

	public function resolveNames(): array
	{
		return array_keys($this->checks);
	}
}
