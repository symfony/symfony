ExpressionLanguage Component
============================

The ExpressionLanguage component provides an engine that can compile and
evaluate expressions:

    use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

    $language = new ExpressionLanguage();

    echo $language->evaluate('1 + foo', array('foo' => 2));
    // would output 3

    echo $language->compile('1 + foo', array('foo'));
    // would output (1 + $foo)

By default, the engine implements simple math and logic functions, method
calls, property accesses, and array accesses.

You can extend your DSL with functions:

    $compiler = function ($arg) {
        return sprintf('strtoupper(%s)', $arg);
    };
    $evaluator = function (array $variables, $value) {
        return strtoupper($value);
    };
    $language->register('upper', $compiler, $evaluator);

    echo $language->evaluate('"foo" ~ upper(foo)', array('foo' => 'bar'));
    // would output fooBAR

    echo $language->compile('"foo" ~ upper(foo)');
    // would output ("foo" . strtoupper($foo))

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/ExpressionLanguage/
    $ composer.phar install --dev
    $ phpunit
