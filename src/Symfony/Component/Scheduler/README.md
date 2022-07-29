Scheduler Component
====================

Provides basic scheduling through the Symfony Messenger.

Getting Started
---------------

```
$ composer require symfony/scheduler
```

Full DSN with schedule name: `schedule://<name>`

```yaml
# messenger.yaml
framework:
  messenger:
    transports:
      schedule_default: 'schedule://default'
```

```php
<?php

use Symfony\Component\Scheduler\ScheduleConfig;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

class ExampleLocator implements ScheduleConfigLocatorInterface
{
    public function get(string $id): ScheduleConfig
    {
        return (new ScheduleConfig())
            ->add(
                // do the MaintenanceJob every night at 3 a.m. UTC
                PeriodicalTrigger::create('P1D', '03:00:00+00'),
                new MaintenanceJob()
            )
        ;
    }

    public function has(string $id): bool
    {
        return 'default' === $id;
    }
}
```

Resources
---------

* [Documentation](https://symfony.com/doc/current/scheduler.html)
* [Contributing](https://symfony.com/doc/current/contributing/index.html)
* [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
