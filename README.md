# HealthchecksBundle

[![Dependency Status](https://www.versioneye.com/user/projects/59187f4ca786140047b4cd11/badge.svg?style=flat)](https://www.versioneye.com/user/projects/59187f4ca786140047b4cd11)
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
