<?php

//@formatter:off
declare(strict_types=1);
//@formatter:on

/**
 * Created by umitakkaya.
 * Date: 28/08/2017
 * Time: 15:43
 */

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