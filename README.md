# HealthchecksBundle

[![Dependency Status](https://beta.gemnasium.com/badges/github.com/prgTW/healthchecks-bundle.svg)](https://beta.gemnasium.com/projects/github.com/prgTW/healthchecks-bundle)
[![Packagist](https://img.shields.io/packagist/l/prgtw/healthchecks-bundle.svg)](https://github.com/prgTW/healthchecks-bundle)
[![Packagist](https://img.shields.io/packagist/v/prgtw/healthchecks-bundle.svg)](https://packagist.org/packages/prgtw/healthchecks-bundle)

Bundle enables integration with [healthchecks.io](https://healthchecks.io).

## TODO:
- [ ] implement concurrent requests

## Installation

1. Require the `prgtw/healthchecks-bundle` package in your `composer.json`
   and update your dependencies.
	
	```bash
	composer require prgtw/healthchecks-bundle
	```

2. Add the `HealthchecksBundle` to your application's kernel:

	```php
	public function registerBundles()
	{
		$bundles = [
			// ...
			new prgTW\HealthchecksBundle(),
			// ...
		];
		// ...
	}
	````

## Configuration

Example configuration:
```yaml
healthchecks:
    api:
        clients:
            example: "api-key-here"
    timezone: "Europe/Warsaw" # default timezone to use for checks
    checks:
    	simple:
            client: example
            name: "Simple hourly check"
            timeout: 3600
            tags: [simple, all]
        cron:
            client: example
            name: "Cron-based check"
            schedule: "*/30 * * * *"
            timezone: UTC
            tags: [cron, all]
```

## Usage

```php
$api = $container->get('healthchecks.api');

// setup checks on healthchecks.io side according to configuration
$api->setup('simple');
$api->setupMany(['simple', 'cron']);

// ping check(s)
$api->ping('simple');
$api->pingMany(['simple', 'cron']);

// pause check(s)
$api->pause('simple');
$api->pauseMany(['simple', 'cron']);
```

## Providing checks data at runtime

To provide checks data at runtime you have to create your own resolver, and you have to configure bundle to use this resolver instead of default one

For example:

```php
namespace App\Healthchecks\Resolver;

use prgTW\HealthchecksBundle\Resolver\ResolverInterface;

class CustomResolver implements ResolverInterface
{
	public function resolve()
	{
		// Get the data from your source and map your array in such format: 
		return [
			'backup_task'  => [
				'name'     => 'Backup task',
				'schedule' => '15 2 * * *',
				'client'   => 'dev',
				'tags'     => ['backup', 'devops'],
				'unique'   => ['name', 'tags'],
			],
			'cleanup_task' => [
				'name'     => 'Cleanup task',
				'schedule' => '0 3 * * *',
				'client'   => 'dev',
				'tags'     => ['backup', 'devops'],
				'unique'   => ['name', 'tags'],
			],
		];
	}

	public function resolveNames(): array
	{
		return ['backup_task', 'cleanup_task'];
	}
}
```

```yaml
services:
    healthchecks.resolver.custom:
        class: "App\\Healthchecks\\Resolver\\CustomResolver"
```


```yaml
healthchecks:
    api:
        clients:
            example: "api-key-here"
    timezone: "Europe/Warsaw" # default timezone to use for checks
    resolver: "healthchecks.resolver.custom" #Service ID of your custom resolver 
```

