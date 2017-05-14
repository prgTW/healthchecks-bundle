<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\IO;

use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\ExclusionPolicy("ALL")
 */
class Checks
{
	/**
	 * @var Check[]
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("checks")
	 * @Serializer\Type("array<prgTW\HealthchecksBundle\IO\Check>")
	 * @Serializer\Accessor(getter="getChecks")
	 */
	protected $checks;

	/**
	 * @return Check[]
	 */
	public function getChecks(): array
	{
		return $this->checks;
	}
}
