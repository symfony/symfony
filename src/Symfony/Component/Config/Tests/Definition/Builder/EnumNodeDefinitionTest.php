<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;

class EnumNodeDefinitionTest extends TestCase
{
    public function testWithOneValue()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo']);

        $node = $def->getNode();
        self::assertEquals(['foo'], $node->getValues());
    }

    public function testWithOneDistinctValue()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo', 'foo']);

        $node = $def->getNode();
        self::assertEquals(['foo'], $node->getValues());
    }

    public function testNoValuesPassed()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('You must call ->values() on enum nodes.');
        $def = new EnumNodeDefinition('foo');
        $def->getNode();
    }

    public function testWithNoValues()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('->values() must be called with at least one value.');
        $def = new EnumNodeDefinition('foo');
        $def->values([]);
    }

    public function testGetNode()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo', 'bar']);

        $node = $def->getNode();
        self::assertEquals(['foo', 'bar'], $node->getValues());
    }

    public function testSetDeprecated()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo', 'bar']);
        $def->setDeprecated('vendor/package', '1.1', 'The "%path%" node is deprecated.');

        $node = $def->getNode();

        self::assertTrue($node->isDeprecated());
        $deprecation = $def->getNode()->getDeprecation($node->getName(), $node->getPath());
        self::assertSame('The "foo" node is deprecated.', $deprecation['message']);
        self::assertSame('vendor/package', $deprecation['package']);
        self::assertSame('1.1', $deprecation['version']);
    }
}
