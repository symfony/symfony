CallableWrapper Component
=========================

This component implements the [Decorator Pattern](https://en.wikipedia.org/wiki/Decorator_pattern) around
any [PHP callable](https://www.php.net/manual/en/language.types.callable.php), allowing you to:
* Execute logic before or after a callable is executed
* Skip the execution of a callable by returning earlier
* Modify the result of a callable

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```bash
composer require symfony/callable-wrapper
```

```php
use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttribute;
use Symfony\Component\CallableWrapper\CallableWrapper;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Debug extends CallableWrapperAttribute implements CallableWrapperInterface
{
    public function wrap(\Closure $func): \Closure
    {
        return function (mixed ...$args) use ($func): mixed
        {
            echo "Do something before\n";

            $result = $func(...$args);

            echo "Do something after\n";

            return $result;
        };
    }
}

class Greeting
{
    #[Debug]
    public function sayHello(string $name): void
    {
        echo "Hello $name!\n";
    }
}

$greeting = new Greeting();
$CallableWrapper = new CallableWrapper();
$CallableWrapper->call($greeting->sayHello(...), 'Fabien');
```
Output:
```
Do something before
Hello Fabien!
Do something after
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/callable-wrapper.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
