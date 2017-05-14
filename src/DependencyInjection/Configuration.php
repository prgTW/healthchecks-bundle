<?php

declare(strict_types=1);

namespace prgTW\HealthchecksBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** @var string */
    protected $alias;

    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

	/** {@inheritdoc} */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode    = $treeBuilder->root($this->alias);

		$this->addApiSection($rootNode);
		$this->addChecksSection($rootNode);

		$rootNode
			->children()
				->scalarNode('timezone')
					->info('Default timezone to use for checks')
					->defaultValue('Europe/Warsaw')
					->validate()
					->ifTrue(function(string $timezone) {
						return false === in_array($timezone, \DateTimeZone::listIdentifiers(), true);
					})
					->thenInvalid('Invalid timezone supplied')
					->end()
				->end()
			->end()
		;

		return $treeBuilder;
	}

	protected function addApiSection(ArrayNodeDefinition $rootNode)
	{
		$rootNode
			->children()
				->arrayNode('api')
					->isRequired()
					->children()
						->arrayNode('clients')
							->useAttributeAsKey('name')
							->isRequired()
							->prototype('scalar')
							->end()
						->end()
						->scalarNode('base_uri')
							->info('Base API uri')
							->defaultValue('https://healthchecks.io')
							->cannotBeEmpty()
						->end()
					->end()
				->end()
			->end()
		;
	}

	protected function addChecksSection(ArrayNodeDefinition $rootNode)
	{
		$rootNode
			->fixXmlConfig('check')
			->children()
				->arrayNode('checks')
					->useAttributeAsKey('id')
					->prototype('array')
                        ->addDefaultsIfNotSet()
						->children()
							->scalarNode('client')
								->info('API client to use')
								->cannotBeEmpty()
							->end()
							->scalarNode('name')
								->info('Name of the check')
								->cannotBeEmpty()
							->end()
							->integerNode('timeout')
								->info('A number of seconds, the expected period of this check')
								->defaultNull()
								->validate()
									->ifTrue(function(int $value) {
										return $value < 60 || $value > 604800;
									})
									->thenInvalid('Minimum: 60 (one minute), maximum: 604800 (one week)')
								->end()
							->end()
							->integerNode('grace')
								->info('A number of seconds, the grace period for this check')
								->defaultValue(3600)
								->validate()
									->ifTrue(function(int $value) {
										return $value < 60 || $value > 604800;
									})
									->thenInvalid('Minimum: 60 (one minute), maximum: 604800 (one week)')
								->end()
							->end()
							->scalarNode('schedule')
								->info('A cron expression defining this check\'s schedule')
								->example('0,30 * * * *')
								->defaultValue('* * * * *')
							->end()
							->scalarNode('timezone')
								->info('Server\'s timezone. This setting only has effect in combination with the "schedule" parameter')
								->example('Europe/Warsaw')
								->defaultNull()
							->end()
							->arrayNode('tags')
								->defaultValue([])
								->prototype('scalar')
								->end()
							->end()
							->arrayNode('channels')
								->info('Names of notification channels for this check. "*" assigns all existing notification channels. "" unassigns all notification channels')
								->defaultValue(['*'])
								->prototype('scalar')
								->end()
							->end()
                            ->arrayNode('unique')
                                ->info('Names of notification channels for this check. "*" assigns all existing notification channels. "" unassigns all notification channels')
                                ->defaultValue(['name'])
                                ->prototype('scalar')
                                    ->validate()
                                        ->ifNotInArray(['name', 'tags', 'timeout', 'grace'])
                                        ->thenInvalid('"unique" can be composed only of ["name", "tags", "timeout", "grace"] values')
                                    ->end()
                                ->end()
                            ->end()
						->end()
					->end()
				->end()
			->end()
		;
	}
}
