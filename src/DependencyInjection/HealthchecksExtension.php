<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Loader;

class HealthchecksExtension extends ConfigurableExtension
{
    /** @var string */
    protected $alias;

    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /** {@inheritdoc} */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('healthchecks-services.yml');

        $checkIdToClient = [];

        $availableClientNames = array_keys($mergedConfig['api']['clients']);
        $clientNames = array_unique(array_values($checkIdToClient));
        $missingClientNames = array_diff($clientNames, $availableClientNames);
        if (count($missingClientNames)) {
            throw new \InvalidArgumentException(
                vsprintf(
                    'Undefined clients: %s',
                    [
                        implode(', ', $missingClientNames),
                    ]
                )
            );
        }

        foreach ($mergedConfig['checks'] as $checkName => $check) {
            $mergedConfig['checks'][$checkName]['timezone'] = $check['timezone'] ?? $mergedConfig['timezone'];
        }

        $apiDefinition = $container->findDefinition('healthchecks.api');
        $apiDefinition->replaceArgument(0, $mergedConfig['api']['clients']);
        $apiDefinition->replaceArgument(1, $mergedConfig['api']['base_uri']);
        $apiDefinition->replaceArgument(2, $mergedConfig['checks']);

        $container->setParameter('healthchecks.check_names', array_keys($mergedConfig['checks']));
    }

    /** {@inheritdoc} */
    public function getAlias()
    {
        return $this->alias;
    }

    /** {@inheritdoc} */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($this->alias);
    }
}
