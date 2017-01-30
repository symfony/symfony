<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container30;

use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
        public static function getStatic()
        {
        }

        final public function getFinal()
        {
        }

        public function &getRef()
        {
        }

        public function getParam($a = null)
        {
        }

        private function getPrivate()
        {
        }
    }

    final class Bar
    {
    }
}

$container = new ContainerBuilder();

$container->register('foo', Foo::class);
$container->register('bar', Bar::class);
$container->register('baz', Bar::class)->setFactory('foo');

return $container;
