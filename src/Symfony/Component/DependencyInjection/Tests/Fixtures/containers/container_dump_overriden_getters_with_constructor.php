<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container34;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

if (!class_exists(Foo::class, false)) {
    abstract class Foo
    {
        protected $bar;

        public function __construct($bar = 'bar')
        {
            $this->bar = $bar;
        }

        abstract public function getPublic();
        abstract protected function getProtected();

        public function getSelf()
        {
            return 123;
        }

        public function getInvalid()
        {
            return 456;
        }

        public function getGetProtected()
        {
            return $this->getProtected();
        }
    }

    class Baz
    {
        final public function __construct()
        {
        }

        protected function getBaz()
        {
            return 234;
        }
    }
}

$container = new ContainerBuilder();

$container
    ->register('foo', Foo::class)
    ->setOverriddenGetter('getPublic', 'public')
    ->setOverriddenGetter('getProtected', 'protected')
    ->setOverriddenGetter('getSelf', new Reference('foo'))
;

$container
    ->register('baz', Baz::class)
    ->setOverriddenGetter('getBaz', 'baz')
;

return $container;
