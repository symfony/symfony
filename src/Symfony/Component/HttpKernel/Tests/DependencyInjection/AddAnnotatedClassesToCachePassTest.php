<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\AddAnnotatedClassesToCachePass;

class AddAnnotatedClassesToCachePassTest extends TestCase
{
    public function testExpandClasses()
    {
        $r = new \ReflectionClass(AddAnnotatedClassesToCachePass::class);
        $pass = $r->newInstanceWithoutConstructor();
        $r = new \ReflectionMethod(AddAnnotatedClassesToCachePass::class, 'expandClasses');
        $expand = $r->getClosure($pass);

        $this->assertSame('Foo', $expand(['Foo'], [])[0]);
        $this->assertSame('Foo', $expand(['\\Foo'], [])[0]);
        $this->assertSame('Foo', $expand(['Foo'], ['\\Foo'])[0]);
        $this->assertSame('Foo', $expand(['Foo'], ['Foo'])[0]);
        $this->assertSame('Foo', $expand(['\\Foo'], ['\\Foo\\Bar'])[0]);
        $this->assertSame('Foo', $expand(['Foo'], ['\\Foo\\Bar'])[0]);
        $this->assertSame('Foo', $expand(['\\Foo'], ['\\Foo\\Bar\\Acme'])[0]);

        $this->assertSame('Foo\\Bar', $expand(['Foo\\'], ['\\Foo\\Bar'])[0]);
        $this->assertSame('Foo\\Bar\\Acme', $expand(['Foo\\'], ['\\Foo\\Bar\\Acme'])[0]);
        $this->assertEmpty($expand(['Foo\\'], ['\\Foo']));

        $this->assertSame('Acme\\Foo\\Bar', $expand(['**\\Foo\\'], ['\\Acme\\Foo\\Bar'])[0]);
        $this->assertEmpty($expand(['**\\Foo\\'], ['\\Foo\\Bar']));
        $this->assertEmpty($expand(['**\\Foo\\'], ['\\Acme\\Foo']));
        $this->assertEmpty($expand(['**\\Foo\\'], ['\\Foo']));

        $this->assertSame('Acme\\Foo', $expand(['**\\Foo'], ['\\Acme\\Foo'])[0]);
        $this->assertEmpty($expand(['**\\Foo'], ['\\Acme\\Foo\\AcmeBundle']));
        $this->assertEmpty($expand(['**\\Foo'], ['\\Acme\\FooBar\\AcmeBundle']));

        $this->assertSame('Foo\\Acme\\Bar', $expand(['Foo\\*\\Bar'], ['\\Foo\\Acme\\Bar'])[0]);
        $this->assertEmpty($expand(['Foo\\*\\Bar'], ['\\Foo\\Acme\\Bundle\\Bar']));

        $this->assertSame('Foo\\Acme\\Bar', $expand(['Foo\\**\\Bar'], ['\\Foo\\Acme\\Bar'])[0]);
        $this->assertSame('Foo\\Acme\\Bundle\\Bar', $expand(['Foo\\**\\Bar'], ['\\Foo\\Acme\\Bundle\\Bar'])[0]);

        $this->assertSame('Acme\\Bar', $expand(['*\\Bar'], ['\\Acme\\Bar'])[0]);
        $this->assertEmpty($expand(['*\\Bar'], ['\\Bar']));
        $this->assertEmpty($expand(['*\\Bar'], ['\\Foo\\Acme\\Bar']));

        $this->assertSame('Foo\\Acme\\Bar', $expand(['**\\Bar'], ['\\Foo\\Acme\\Bar'])[0]);
        $this->assertSame('Foo\\Acme\\Bundle\\Bar', $expand(['**\\Bar'], ['\\Foo\\Acme\\Bundle\\Bar'])[0]);
        $this->assertEmpty($expand(['**\\Bar'], ['\\Bar']));

        $this->assertSame('Foo\\Bar', $expand(['Foo\\*'], ['\\Foo\\Bar'])[0]);
        $this->assertEmpty($expand(['Foo\\*'], ['\\Foo\\Acme\\Bar']));

        $this->assertSame('Foo\\Bar', $expand(['Foo\\**'], ['\\Foo\\Bar'])[0]);
        $this->assertSame('Foo\\Acme\\Bar', $expand(['Foo\\**'], ['\\Foo\\Acme\\Bar'])[0]);

        $this->assertSame(['Foo\\Bar'], $expand(['Foo\\*'], ['Foo\\Bar', 'Foo\\BarTest']));
        $this->assertSame(['Foo\\Bar', 'Foo\\BarTest'], $expand(['Foo\\*', 'Foo\\*Test'], ['Foo\\Bar', 'Foo\\BarTest']));

        $this->assertSame(
            'Acme\\FooBundle\\Controller\\DefaultController',
            $expand(['**Bundle\\Controller\\'], ['\\Acme\\FooBundle\\Controller\\DefaultController'])[0]
        );

        $this->assertSame(
            'FooBundle\\Controller\\DefaultController',
            $expand(['**Bundle\\Controller\\'], ['\\FooBundle\\Controller\\DefaultController'])[0]
        );

        $this->assertSame(
            'Acme\\FooBundle\\Controller\\Bar\\DefaultController',
            $expand(['**Bundle\\Controller\\'], ['\\Acme\\FooBundle\\Controller\\Bar\\DefaultController'])[0]
        );

        $this->assertSame(
            'Bundle\\Controller\\Bar\\DefaultController',
            $expand(['**Bundle\\Controller\\'], ['\\Bundle\\Controller\\Bar\\DefaultController'])[0]
        );

        $this->assertSame(
            'Acme\\Bundle\\Controller\\Bar\\DefaultController',
            $expand(['**Bundle\\Controller\\'], ['\\Acme\\Bundle\\Controller\\Bar\\DefaultController'])[0]
        );

        $this->assertSame('Foo\\Bar', $expand(['Foo\\Bar'], [])[0]);
        $this->assertSame('Foo\\Acme\\Bar', $expand(['Foo\\**'], ['\\Foo\\Acme\\Bar'])[0]);
    }
}
