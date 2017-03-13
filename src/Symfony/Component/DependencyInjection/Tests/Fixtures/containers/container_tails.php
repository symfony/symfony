<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Tails;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
        public function method0($bar)
        {
            return func_get_args();
        }

        public function method1($arg)
        {
            return func_get_args();
        }
    }

    class Baz
    {
        final public function __construct()
        {
        }

        protected function method1($arg1, $arg2)
        {
            return func_get_args();
        }
    }
}

$container = new ContainerBuilder();

$container
    ->register('foo', Foo::class)
    ->setOverridenTail('method1', array(123))
;

$container
    ->register('baz', Baz::class)
    ->setOverridenTail('method1', array(1 => new Reference('foo')))
;

return $container;
