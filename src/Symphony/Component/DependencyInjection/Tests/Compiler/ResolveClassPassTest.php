<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ChildDefinition;
use Symphony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;

class ResolveClassPassTest extends TestCase
{
    /**
     * @dataProvider provideValidClassId
     */
    public function testResolveClassFromId($serviceId)
    {
        $container = new ContainerBuilder();
        $def = $container->register($serviceId);

        (new ResolveClassPass())->process($container);

        $this->assertSame($serviceId, $def->getClass());
    }

    public function provideValidClassId()
    {
        yield array('Acme\UnknownClass');
        yield array(CaseSensitiveClass::class);
    }

    /**
     * @dataProvider provideInvalidClassId
     */
    public function testWontResolveClassFromId($serviceId)
    {
        $container = new ContainerBuilder();
        $def = $container->register($serviceId);

        (new ResolveClassPass())->process($container);

        $this->assertNull($def->getClass());
    }

    public function provideInvalidClassId()
    {
        yield array(\stdClass::class);
        yield array('bar');
        yield array('\DateTime');
    }

    public function testNonFqcnChildDefinition()
    {
        $container = new ContainerBuilder();
        $parent = $container->register('App\Foo', null);
        $child = $container->setDefinition('App\Foo.child', new ChildDefinition('App\Foo'));

        (new ResolveClassPass())->process($container);

        $this->assertSame('App\Foo', $parent->getClass());
        $this->assertNull($child->getClass());
    }

    public function testClassFoundChildDefinition()
    {
        $container = new ContainerBuilder();
        $parent = $container->register('App\Foo', null);
        $child = $container->setDefinition(self::class, new ChildDefinition('App\Foo'));

        (new ResolveClassPass())->process($container);

        $this->assertSame('App\Foo', $parent->getClass());
        $this->assertSame(self::class, $child->getClass());
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Service definition "App\Foo\Child" has a parent but no class, and its name looks like a FQCN. Either the class is missing or you want to inherit it from the parent service. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class.
     */
    public function testAmbiguousChildDefinition()
    {
        $container = new ContainerBuilder();
        $parent = $container->register('App\Foo', null);
        $child = $container->setDefinition('App\Foo\Child', new ChildDefinition('App\Foo'));

        (new ResolveClassPass())->process($container);
    }
}
