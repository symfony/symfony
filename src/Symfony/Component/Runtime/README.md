Runtime Component
=================

Symfony Runtime enables decoupling applications from global state.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```
$ composer require symfony/runtime
```

RuntimeInterface
----------------

The core of this component is the `RuntimeInterface` which describes a high-order
runtime logic.

It is designed to be totally generic and able to run any application outside of
the global state in 6 steps:

 1. the main entry point returns a callable that wraps the application;
 2. this callable is passed to `RuntimeInterface::getResolver()`, which returns a
    `ResolverInterface`; this resolver returns an array with the (potentially
    decorated) callable at index 0, and all its resolved arguments at index 1;
 3. the callable is invoked with its arguments; it returns an object that
    represents the application;
 4. that object is passed to `RuntimeInterface::getRunner()`, which returns a
    `RunnerInterface`: an instance that knows how to "run" the object;
 5. that instance is `run()` and returns the exit status code as `int`;
 6. the PHP engine is exited with this status code.

This process is extremely flexible as it allows implementations of
`RuntimeInterface` to hook into any critical steps.

Autoloading
-----------

This package registers itself as a Composer plugin to generate a
`vendor/autoload_runtime.php` file. This file shall be required instead of the
usual `vendor/autoload.php` in front-controllers that leverage this component
and return a callable.

Before requiring the `vendor/autoload_runtime.php` file, set the
`$_SERVER['APP_RUNTIME']` variable to a class that implements `RuntimeInterface`
and that should be used to run the returned callable.

Alternatively, the class of the runtime can be defined in the `extra.runtime.class`
entry of the `composer.json` file.

A `SymfonyRuntime` is used by default. It knows the conventions to run
Symfony and native PHP applications.

Examples
--------

This `public/index.php` is a "Hello World" that handles a "name" query parameter:
```php
<?php

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $request, array $context): void {
    // $request holds keys "query", "body", "files" and "session",
    // which map to $_GET, $_POST, $_FILES and &$_SESSION respectively

    // $context maps to $_SERVER

    $name = $request['query']['name'] ?? 'World';
    $time = $context['REQUEST_TIME'];

    echo sprintf('Hello %s, the current Unix timestamp is %s.', $name, $time);
};
```

This `bin/console.php` is a single-command "Hello World" application
(run `composer require symfony/console` before launching it):
```php
<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (Command $command) {
    $command->addArgument('name', null, 'Who should I greet?', 'World');

    return $command->setCode(function (InputInterface $input, OutputInterface $output) {
        $name = $input->getArgument('name');
        $output->writeln(sprintf('Hello <comment>%s</>', $name));
    });
};
```

The `SymfonyRuntime` can resolve and handle many types related to the
`symfony/http-foundation` and `symfony/console` components.
Check its source code for more information.

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
