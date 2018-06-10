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
        $r->setAccessible(true);
        $expand = $r->getClosure($pass);

        $this->assertSame('Foo', $expand(array('Foo'), array())[0]);
        $this->assertSame('Foo', $expand(array('\\Foo'), array())[0]);
        $this->assertSame('Foo', $expand(array('Foo'), array('\\Foo'))[0]);
        $this->assertSame('Foo', $expand(array('Foo'), array('Foo'))[0]);
        $this->assertSame('Foo', $expand(array('\\Foo'), array('\\Foo\\Bar'))[0]);
        $this->assertSame('Foo', $expand(array('Foo'), array('\\Foo\\Bar'))[0]);
        $this->assertSame('Foo', $expand(array('\\Foo'), array('\\Foo\\Bar\\Acme'))[0]);

        $this->assertSame('Foo\\Bar', $expand(array('Foo\\'), array('\\Foo\\Bar'))[0]);
        $this->assertSame('Foo\\Bar\\Acme', $expand(array('Foo\\'), array('\\Foo\\Bar\\Acme'))[0]);
        $this->assertEmpty($expand(array('Foo\\'), array('\\Foo')));

        $this->assertSame('Acme\\Foo\\Bar', $expand(array('**\\Foo\\'), array('\\Acme\\Foo\\Bar'))[0]);
        $this->assertEmpty($expand(array('**\\Foo\\'), array('\\Foo\\Bar')));
        $this->assertEmpty($expand(array('**\\Foo\\'), array('\\Acme\\Foo')));
        $this->assertEmpty($expand(array('**\\Foo\\'), array('\\Foo')));

        $this->assertSame('Acme\\Foo', $expand(array('**\\Foo'), array('\\Acme\\Foo'))[0]);
        $this->assertEmpty($expand(array('**\\Foo'), array('\\Acme\\Foo\\AcmeBundle')));
        $this->assertEmpty($expand(array('**\\Foo'), array('\\Acme\\FooBar\\AcmeBundle')));

        $this->assertSame('Foo\\Acme\\Bar', $expand(array('Foo\\*\\Bar'), array('\\Foo\\Acme\\Bar'))[0]);
        $this->assertEmpty($expand(array('Foo\\*\\Bar'), array('\\Foo\\Acme\\Bundle\\Bar')));

        $this->assertSame('Foo\\Acme\\Bar', $expand(array('Foo\\**\\Bar'), array('\\Foo\\Acme\\Bar'))[0]);
        $this->assertSame('Foo\\Acme\\Bundle\\Bar', $expand(array('Foo\\**\\Bar'), array('\\Foo\\Acme\\Bundle\\Bar'))[0]);

        $this->assertSame('Acme\\Bar', $expand(array('*\\Bar'), array('\\Acme\\Bar'))[0]);
        $this->assertEmpty($expand(array('*\\Bar'), array('\\Bar')));
        $this->assertEmpty($expand(array('*\\Bar'), array('\\Foo\\Acme\\Bar')));

        $this->assertSame('Foo\\Acme\\Bar', $expand(array('**\\Bar'), array('\\Foo\\Acme\\Bar'))[0]);
        $this->assertSame('Foo\\Acme\\Bundle\\Bar', $expand(array('**\\Bar'), array('\\Foo\\Acme\\Bundle\\Bar'))[0]);
        $this->assertEmpty($expand(array('**\\Bar'), array('\\Bar')));

        $this->assertSame('Foo\\Bar', $expand(array('Foo\\*'), array('\\Foo\\Bar'))[0]);
        $this->assertEmpty($expand(array('Foo\\*'), array('\\Foo\\Acme\\Bar')));

        $this->assertSame('Foo\\Bar', $expand(array('Foo\\**'), array('\\Foo\\Bar'))[0]);
        $this->assertSame('Foo\\Acme\\Bar', $expand(array('Foo\\**'), array('\\Foo\\Acme\\Bar'))[0]);

        $this->assertSame(array('Foo\\Bar'), $expand(array('Foo\\*'), array('Foo\\Bar', 'Foo\\BarTest')));
        $this->assertSame(array('Foo\\Bar', 'Foo\\BarTest'), $expand(array('Foo\\*', 'Foo\\*Test'), array('Foo\\Bar', 'Foo\\BarTest')));

        $this->assertSame(
            'Acme\\FooBundle\\Controller\\DefaultController',
            $expand(array('**Bundle\\Controller\\'), array('\\Acme\\FooBundle\\Controller\\DefaultController'))[0]
        );

        $this->assertSame(
            'FooBundle\\Controller\\DefaultController',
            $expand(array('**Bundle\\Controller\\'), array('\\FooBundle\\Controller\\DefaultController'))[0]
        );

        $this->assertSame(
            'Acme\\FooBundle\\Controller\\Bar\\DefaultController',
            $expand(array('**Bundle\\Controller\\'), array('\\Acme\\FooBundle\\Controller\\Bar\\DefaultController'))[0]
        );

        $this->assertSame(
            'Bundle\\Controller\\Bar\\DefaultController',
            $expand(array('**Bundle\\Controller\\'), array('\\Bundle\\Controller\\Bar\\DefaultController'))[0]
        );

        $this->assertSame(
            'Acme\\Bundle\\Controller\\Bar\\DefaultController',
            $expand(array('**Bundle\\Controller\\'), array('\\Acme\\Bundle\\Controller\\Bar\\DefaultController'))[0]
        );

        $this->assertSame('Foo\\Bar', $expand(array('Foo\\Bar'), array())[0]);
        $this->assertSame('Foo\\Acme\\Bar', $expand(array('Foo\\**'), array('\\Foo\\Acme\\Bar'))[0]);
    }
}
