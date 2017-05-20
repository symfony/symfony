<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container31;

use Symfony\Component\DependencyInjection\Argument\ClosureProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
        public function withNoArgs()
        {
        }

        public function withArgs(parent $a, self $b = null, $c = array(123))
        {
        }

        public function &withRefs(&$a = null, &$b)
        {
        }
    }
}

$container = new ContainerBuilder();

$container->register('foo', Foo::class);

$container->register('bar', 'stdClass')
    ->setProperty('foo', array(
        new ClosureProxyArgument('foo', 'withNoArgs'),
        new ClosureProxyArgument('foo', 'withArgs'),
        new ClosureProxyArgument('foo', 'withRefs'),
    ))
;

return $container;
