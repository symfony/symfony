<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;

class ChildDefinitionTest extends TestCase
{
    public function testConstructor()
    {
        $def = new ChildDefinition('foo');

        self::assertSame('foo', $def->getParent());
        self::assertSame([], $def->getChanges());
    }

    /**
     * @dataProvider getPropertyTests
     */
    public function testSetProperty($property, $changeKey)
    {
        $def = new ChildDefinition('foo');

        $getter = 'get'.ucfirst($property);
        $setter = 'set'.ucfirst($property);

        self::assertNull($def->$getter());
        self::assertSame($def, $def->$setter('foo'));
        self::assertSame('foo', $def->$getter());
        self::assertSame([$changeKey => true], $def->getChanges());
    }

    public function getPropertyTests()
    {
        return [
            ['class', 'class'],
            ['factory', 'factory'],
            ['configurator', 'configurator'],
            ['file', 'file'],
        ];
    }

    public function testSetPublic()
    {
        $def = new ChildDefinition('foo');

        self::assertFalse($def->isPublic());
        self::assertSame($def, $def->setPublic(true));
        self::assertTrue($def->isPublic());
        self::assertSame(['public' => true], $def->getChanges());
    }

    public function testSetLazy()
    {
        $def = new ChildDefinition('foo');

        self::assertFalse($def->isLazy());
        self::assertSame($def, $def->setLazy(false));
        self::assertFalse($def->isLazy());
        self::assertSame(['lazy' => true], $def->getChanges());
    }

    public function testSetAutowired()
    {
        $def = new ChildDefinition('foo');

        self::assertFalse($def->isAutowired());
        self::assertSame($def, $def->setAutowired(true));
        self::assertTrue($def->isAutowired());
        self::assertSame(['autowired' => true], $def->getChanges());
    }

    public function testSetArgument()
    {
        $def = new ChildDefinition('foo');

        self::assertSame([], $def->getArguments());
        self::assertSame($def, $def->replaceArgument(0, 'foo'));
        self::assertSame(['index_0' => 'foo'], $def->getArguments());
    }

    public function testReplaceArgumentShouldRequireIntegerIndex()
    {
        self::expectException(\InvalidArgumentException::class);
        $def = new ChildDefinition('foo');

        $def->replaceArgument('0', 'foo');
    }

    public function testReplaceArgument()
    {
        $def = new ChildDefinition('foo');

        $def->setArguments([0 => 'foo', 1 => 'bar']);
        self::assertSame('foo', $def->getArgument(0));
        self::assertSame('bar', $def->getArgument(1));

        self::assertSame($def, $def->replaceArgument(1, 'baz'));
        self::assertSame('foo', $def->getArgument(0));
        self::assertSame('baz', $def->getArgument(1));

        self::assertSame([0 => 'foo', 1 => 'bar', 'index_1' => 'baz'], $def->getArguments());

        self::assertSame($def, $def->replaceArgument('$bar', 'val'));
        self::assertSame('val', $def->getArgument('$bar'));
        self::assertSame([0 => 'foo', 1 => 'bar', 'index_1' => 'baz', '$bar' => 'val'], $def->getArguments());
    }

    public function testGetArgumentShouldCheckBounds()
    {
        self::expectException(\OutOfBoundsException::class);
        $def = new ChildDefinition('foo');

        $def->setArguments([0 => 'foo']);
        $def->replaceArgument(0, 'foo');

        $def->getArgument(1);
    }

    public function testAutoconfigured()
    {
        $def = new ChildDefinition('foo');
        $def->setAutoconfigured(true);

        self::assertTrue($def->isAutoconfigured());
    }

    public function testInstanceofConditionals()
    {
        $conditionals = ['Foo' => new ChildDefinition('')];
        $def = new ChildDefinition('foo');
        $def->setInstanceofConditionals($conditionals);

        self::assertSame($conditionals, $def->getInstanceofConditionals());
    }
}
