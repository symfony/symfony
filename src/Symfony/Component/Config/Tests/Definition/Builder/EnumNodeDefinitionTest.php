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
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\TestEnum;

class EnumNodeDefinitionTest extends TestCase
{
    public function testWithOneValue()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo']);

        $node = $def->getNode();
        $this->assertEquals(['foo'], $node->getValues());
    }

    public function testWithUnitEnumFqcn()
    {
        $def = new EnumNodeDefinition('foo');
        $def->enumFqcn(TestEnum::class);

        $node = $def->getNode();
        $this->assertEquals(TestEnum::class, $node->getEnumFqcn());
    }

    public function testNoValuesPassed()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must call either ->values() or ->enumFqcn() on enum nodes.');
        $def = new EnumNodeDefinition('foo');
        $def->getNode();
    }

    public function testBothValuesAndEnumFqcnPassed()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must call either ->values() or ->enumFqcn() on enum nodes but not both.');
        $def = new EnumNodeDefinition('foo');
        $def->values([123])
            ->enumFqcn(StringBackedTestEnum::class);

        $def->getNode();
    }

    public function testWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('->values() must be called with at least one value.');
        $def = new EnumNodeDefinition('foo');
        $def->values([]);
    }

    public function testGetNode()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo', 'bar']);

        $node = $def->getNode();
        $this->assertEquals(['foo', 'bar'], $node->getValues());
    }

    public function testSetDeprecated()
    {
        $def = new EnumNodeDefinition('foo');
        $def->values(['foo', 'bar']);
        $def->setDeprecated('vendor/package', '1.1', 'The "%path%" node is deprecated.');

        $node = $def->getNode();

        $this->assertTrue($node->isDeprecated());
        $deprecation = $def->getNode()->getDeprecation($node->getName(), $node->getPath());
        $this->assertSame('The "foo" node is deprecated.', $deprecation['message']);
        $this->assertSame('vendor/package', $deprecation['package']);
        $this->assertSame('1.1', $deprecation['version']);
    }

    public function testSameStringCoercedValuesAreDifferent()
    {
        $def = new EnumNodeDefinition('ccc');
        $def->values(['', false, null]);

        $this->assertSame(['', false, null], $def->getNode()->getValues());
    }
}
