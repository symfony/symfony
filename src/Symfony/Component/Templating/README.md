Templating Component
====================

The Templating component provides all the tools needed to build any kind of
template system.

It provides an infrastructure to load template files and optionally monitor them
for changes. It also provides a concrete template engine implementation using
PHP with additional tools for escaping and separating templates into blocks and
layouts.

Getting Started
---------------

```
$ composer require symfony/templating
```

```php
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\Helper\SlotsHelper;
use Symfony\Component\Templating\TemplateNameParser;

$filesystemLoader = new FilesystemLoader(__DIR__.'/views/%name%');

$templating = new PhpEngine(new TemplateNameParser(), $filesystemLoader);
$templating->set(new SlotsHelper());

echo $templating->render('hello.php', ['firstname' => 'Fabien']);

// hello.php
Hello, <?= $view->escape($firstname) ?>!
```

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
