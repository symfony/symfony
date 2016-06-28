<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpKernel\CacheWarmer\ClassMatcher;

class ClassMatcherTest extends TestCase
{
    public function testParsePatterns()
    {
        $matcher = new ClassMatcher();

        $this->assertEquals('Foo', $matcher->match(array(), array('Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array(), array('\\Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array('\\Foo'), array('Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array('Foo'), array('Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array('\\Foo\\Bar'), array('\\Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array('\\Foo\\Bar'), array('Foo'))[0]);
        $this->assertEquals('Foo', $matcher->match(array('\\Foo\\Bar\\Acme'), array('\\Foo'))[0]);

        $this->assertEquals('Foo\\Bar', $matcher->match(array('\\Foo\\Bar'), array('Foo\\'))[0]);
        $this->assertEquals('Foo\\Bar\\Acme', $matcher->match(array('\\Foo\\Bar\\Acme'), array('Foo\\'))[0]);
        $this->assertEmpty($matcher->match(array('\\Foo'), array('Foo\\')));

        $this->assertEquals('Acme\\Foo\\Bar', $matcher->match(array('\\Acme\\Foo\\Bar'), array('**\\Foo\\'))[0]);
        $this->assertEmpty($matcher->match(array('\\Foo\\Bar'), array('**\\Foo\\')));
        $this->assertEmpty($matcher->match(array('\\Acme\\Foo'), array('**\\Foo\\')));
        $this->assertEmpty($matcher->match(array('\\Foo'), array('**\\Foo\\')));

        $this->assertEquals('Acme\\Foo', $matcher->match(array('\\Acme\\Foo'), array('**\\Foo'))[0]);
        $this->assertEmpty($matcher->match(array('\\Acme\\Foo\\AcmeBundle'), array('**\\Foo')));
        $this->assertEmpty($matcher->match(array('\\Acme\\FooBar\\AcmeBundle'), array('**\\Foo')));

        $this->assertEquals('Foo\\Acme\\Bar', $matcher->match(array('\\Foo\\Acme\\Bar'), array('Foo\\*\\Bar'))[0]);
        $this->assertEmpty($matcher->match(array('\\Foo\\Acme\\Bundle\\Bar'), array('Foo\\*\\Bar')));

        $this->assertEquals('Foo\\Acme\\Bar', $matcher->match(array('\\Foo\\Acme\\Bar'), array('Foo\\**\\Bar'))[0]);
        $this->assertEquals('Foo\\Acme\\Bundle\\Bar', $matcher->match(array('\\Foo\\Acme\\Bundle\\Bar'), array('Foo\\**\\Bar'))[0]);

        $this->assertEquals('Acme\\Bar', $matcher->match(array('\\Acme\\Bar'), array('*\\Bar'))[0]);
        $this->assertEmpty($matcher->match(array('\\Bar'), array('*\\Bar')));
        $this->assertEmpty($matcher->match(array('\\Foo\\Acme\\Bar'), array('*\\Bar')));

        $this->assertEquals('Foo\\Acme\\Bar', $matcher->match(array('\\Foo\\Acme\\Bar'), array('**\\Bar'))[0]);
        $this->assertEquals('Foo\\Acme\\Bundle\\Bar', $matcher->match(array('\\Foo\\Acme\\Bundle\\Bar'), array('**\\Bar'))[0]);
        $this->assertEmpty($matcher->match(array('\\Bar'), array('**\\Bar')));

        $this->assertEquals('Foo\\Bar', $matcher->match(array('\\Foo\\Bar'), array('Foo\\*'))[0]);
        $this->assertEmpty($matcher->match(array('\\Foo\\Acme\\Bar'), array('Foo\\*')));

        $this->assertEquals('Foo\\Bar', $matcher->match(array('\\Foo\\Bar'), array('Foo\\**'))[0]);
        $this->assertEquals('Foo\\Acme\\Bar', $matcher->match(array('\\Foo\\Acme\\Bar'), array('Foo\\**'))[0]);

        $this->assertEquals(array('Foo\\Bar'), $matcher->match(array('Foo\\Bar', 'Foo\\BarTest'), array('Foo\\*')));
        $this->assertEquals(array('Foo\\Bar', 'Foo\\BarTest'), $matcher->match(array('Foo\\Bar', 'Foo\\BarTest'), array('Foo\\*', 'Foo\\*Test')));

        $this->assertEquals(
            'Acme\\FooBundle\\Controller\\DefaultController',
            $matcher->match(array('\\Acme\\FooBundle\\Controller\\DefaultController'), array('**Bundle\\Controller\\'))[0]
        );

        $this->assertEquals(
            'FooBundle\\Controller\\DefaultController',
            $matcher->match(array('\\FooBundle\\Controller\\DefaultController'), array('**Bundle\\Controller\\'))[0]
        );

        $this->assertEquals(
            'Acme\\FooBundle\\Controller\\Bar\\DefaultController',
            $matcher->match(array('\\Acme\\FooBundle\\Controller\\Bar\\DefaultController'), array('**Bundle\\Controller\\'))[0]
        );

        $this->assertEquals(
            'Bundle\\Controller\\Bar\\DefaultController',
            $matcher->match(array('\\Bundle\\Controller\\Bar\\DefaultController'), array('**Bundle\\Controller\\'))[0]
        );

        $this->assertEquals(
            'Acme\\Bundle\\Controller\\Bar\\DefaultController',
            $matcher->match(array('\\Acme\\Bundle\\Controller\\Bar\\DefaultController'), array('**Bundle\\Controller\\'))[0]
        );

        $this->assertEquals('Foo\\Bar', $matcher->match(array(), array('Foo\\Bar'))[0]);
        $this->assertEquals('Foo\\Acme\\Bar', $matcher->match(array('\\Foo\\Acme\\Bar'), array('Foo\\**'))[0]);
    }
}
