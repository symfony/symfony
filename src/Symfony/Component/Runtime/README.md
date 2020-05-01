Runtime Component
=================

Symfony Runtime enables decoupling apps from global state.

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

 1. your front-controller returns a closure that wraps your app;
 2. the arguments of this closure are resolved by `RuntimeInterface::resolve()`
    which returns a `ResolvedAppInterface`. This is an invokable with zero
    arguments that returns whatever object of yours represents your app
    (e.g a Symfony kernel or response, a console application or command);
 3. this invokable is called and returns this object that represents your app;
 4. your app object is passed to `RuntimeInterface::start()`, which returns a
    `StartedAppInterface`: an invokable that knows how to "run" your app;
 5. that invokable is called and returns the exit status code as int;
 6. the PHP engine is exited with this status code.

This process is extremely flexible as it allows implementations of
`RuntimeInterface` to hook into any critical steps.

Autoloading
-----------

This package registers itself as a Composer plugin to generate a
`vendor/autoload_runtime.php` file. You need to require it instead of the usual
`vendor/autoload.php` in front-controllers that leverage this component and
return a closure.

Before requiring the `vendor/autoload_runtime.php` file, you  can set the
`$_SERVER['APP_RUNTIME']` variable to a class that implements `RuntimeInterface`
and that should be used to run the app.

A `SymfonyRuntime` is used by default. It knows the conventions to run
Symfony and native PHP apps.

Examples
--------

This `public/index.php` is a "Hello World" that handles a "name" query parameter:
```php
<?php

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $request, array $context): string {
    // $request holds keys "query", "data", "files" and "session",
    // which map to $_GET, $_POST, $_FILES and &$_SESSION respectively

    // $context maps to $_SERVER

    $name = $request['query']['name'] ?? 'World';
    $time = $context['REQUEST_TIME'];

    return sprintf('Hello %s, the current Unix timestamp is %s.', $name, $time);
};
```

This `bin/console.php` is a single-command "Hello World" app
(run `composer require symfony/console` before launching it):
```php
<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (Command $command) {
    $command->addArgument('name', null, 'Who should I greet?', 'World');

    return function (InputInterface $input, OutputInterface $output) {
        $name = $input->getArgument('name');
        $output->writeln(sprintf('Hello <comment>%s</>', $name));
    };
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
