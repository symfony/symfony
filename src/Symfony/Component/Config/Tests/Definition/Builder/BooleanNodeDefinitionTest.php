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
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;

class BooleanNodeDefinitionTest extends TestCase
{
    public function testCannotBeEmptyThrowsAnException()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->cannotBeEmpty() is not applicable to BooleanNodeDefinition.');
        $def = new BooleanNodeDefinition('foo');
        $def->cannotBeEmpty();
    }

    public function testBooleanNodeNotNullable()
    {
        $def = new BooleanNodeDefinition('foo');

        $node = $def->getNode();
        $this->assertFalse($node->hasDefaultValue());
        $this->assertFalse($node->isNullable());

        $this->assertTrue($node->normalize(null));
    }

    public function testBooleanNodeWithDefaultNull()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->defaultNull();

        $node = $def->getNode();
        $this->assertTrue($node->hasDefaultValue());
        $this->assertNull($node->getDefaultValue());
        $this->assertTrue($node->isNullable());

        $this->assertNull($node->normalize(null));
    }

    public function testBooleanNodeWithDefaultValueAtNull()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->defaultValue(null);

        $node = $def->getNode();
        $this->assertTrue($node->hasDefaultValue());
        $this->assertNull($node->getDefaultValue());
        $this->assertTrue($node->isNullable());

        $this->assertNull($node->normalize(null));
    }

    public function testSetDeprecated()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->setDeprecated('vendor/package', '1.1', 'The "%path%" node is deprecated.');

        $node = $def->getNode();

        $this->assertTrue($node->isDeprecated());
        $deprecation = $node->getDeprecation($node->getName(), $node->getPath());
        $this->assertSame('The "foo" node is deprecated.', $deprecation['message']);
        $this->assertSame('vendor/package', $deprecation['package']);
        $this->assertSame('1.1', $deprecation['version']);
        $this->assertSame('Since vendor/package 1.1: The "foo" node is deprecated.', $node->getDeprecationMessage());
    }
}
