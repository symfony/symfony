Stopwatch Component
===================

The Stopwatch component provides a way to profile code.

Getting Started
---------------

```
$ composer require symfony/stopwatch
```

```php
use Symfony\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();

// optionally group events into sections (e.g. phases of the execution)
$stopwatch->openSection();

// starts event named 'eventName'
$stopwatch->start('eventName');

// ... run your code here

// optionally, start a new "lap" time
$stopwatch->lap('foo');

// ... run your code here

$event = $stopwatch->stop('eventName');

$stopwatch->stopSection('phase_1');
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
