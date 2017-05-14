<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle;

use prgTW\HealthchecksBundle\DependencyInjection\HealthchecksExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HealthchecksBundle extends Bundle
{
    /** @var string */
    protected $alias;

    public function __construct(string $alias = 'healthchecks')
    {
        $this->alias = $alias;
    }

    /** {@inheritdoc} */
    public function getContainerExtension()
    {
        return new HealthchecksExtension($this->alias);
    }
}
