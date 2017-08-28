<?php
/**
 * Created by umitakkaya.
 * Date: 28/08/2017
 * Time: 14:56
 */

namespace prgTW\HealthchecksBundle\Resolver;


interface ResolverInterface
{
	public function resolve();

	public function resolveNames(): array;
}