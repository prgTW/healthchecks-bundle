<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\IO;

use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\ExclusionPolicy("ALL")
 */
class Check
{
	/**
	 * @var \DateTimeImmutable|null
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("last_ping")
	 * @Serializer\Type("DateTimeImmutable<'Y-m-d\TH:i:sO'>")
	 * @Serializer\Accessor(getter="getLastPing")
	 */
	protected $lastPing = null;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("ping_url")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getPingUrl")
	 */
	protected $pingUrl;

	/**
	 * @var \DateTimeImmutable|null
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("next_ping")
	 * @Serializer\Type("DateTimeImmutable<'Y-m-d\TH:i:sO'>")
	 * @Serializer\Accessor(getter="getNextPing")
	 */
	protected $nextPing = null;

	/**
	 * @var int
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("grace")
	 * @Serializer\Type("integer")
	 * @Serializer\Accessor(getter="getGrace")
	 */
	protected $grace;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("name")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getName")
	 */
	protected $name;

	/**
	 * @var int
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("n_pings")
	 * @Serializer\Type("integer")
	 * @Serializer\Accessor(getter="getNPings")
	 */
	protected $nPings;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("tags")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getTags")
	 */
	protected $tags;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("pause_url")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getPauseUrl")
	 */
	protected $pauseUrl;

	/**
	 * @var int|null
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("timeout")
	 * @Serializer\Type("integer")
	 * @Serializer\Accessor(getter="getTimeout")
	 */
	protected $timeout;

	/**
	 * @var string|null
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("tz")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getTz")
	 */
	protected $tz;

	/**
	 * @var string|null
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("schedule")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getSchedule")
	 */
	protected $schedule;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("status")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getStatus")
	 */
	protected $status;

	/**
	 * @var string
	 *
	 * @Serializer\Expose()
	 * @Serializer\SerializedName("update_url")
	 * @Serializer\Type("string")
	 * @Serializer\Accessor(getter="getUpdateUrl")
	 */
	protected $updateUrl;

	/**
	 * @return \DateTimeImmutable|null
	 */
	public function getLastPing()
	{
		return $this->lastPing;
	}

	/**
	 * @return string
	 */
	public function getPingUrl(): string
	{
		return $this->pingUrl;
	}

	/**
	 * @return \DateTimeImmutable|null
	 */
	public function getNextPing()
	{
		return $this->nextPing;
	}

	/**
	 * @return int
	 */
	public function getGrace(): int
	{
		return $this->grace;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return int
	 */
	public function getNPings(): int
	{
		return $this->nPings;
	}

	/**
	 * @return string
	 */
	public function getTags(): string
	{
		return $this->tags;
	}

	/**
	 * @return string[]
	 */
	public function getTagsAsArray(): array
	{
		$tags = array_map('trim', explode(' ', $this->tags));

		return $tags;
	}

	/**
	 * @return string
	 */
	public function getPauseUrl(): string
	{
		return $this->pauseUrl;
	}

	/**
	 * @return int|null
	 */
	public function getTimeout()
	{
		return $this->timeout;
	}

	/**
	 * @return string|null
	 */
	public function getTz()
	{
		return $this->tz;
	}

	/**
	 * @return string|null
	 */
	public function getSchedule()
	{
		return $this->schedule;
	}

	/**
	 * @return string
	 */
	public function getStatus(): string
	{
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function getUpdateUrl(): string
	{
		return $this->updateUrl;
	}
}
