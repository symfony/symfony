<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class EnumNodeTest extends TestCase
{
    public function testFinalizeValue()
    {
        $node = new EnumNode('foo', null, ['foo', 'bar']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$values must contain at least one element.');
        new EnumNode('foo', null, []);
    }

    public function testConstructionWithOneValue()
    {
        $node = new EnumNode('foo', null, ['foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithOneDistinctValue()
    {
        $node = new EnumNode('foo', null, ['foo', 'foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithNullName()
    {
        $node = new EnumNode(null, null, ['foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testFinalizeWithInvalidValue()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foobar" is not allowed for path "foo". Permissible values: "foo", "bar"');
        $node = new EnumNode('foo', null, ['foo', 'bar']);
        $node->finalize('foobar');
    }

    public function testWithPlaceHolderWithValidValue()
    {
        $node = new EnumNode('cookie_samesite', null, ['lax', 'strict', 'none']);
        EnumNode::setPlaceholder('custom', ['string' => 'lax']);
        $this->assertSame('custom', $node->finalize('custom'));
    }

    public function testWithPlaceHolderWithInvalidValue()
    {
        $node = new EnumNode('cookie_samesite', null, ['lax', 'strict', 'none']);
        EnumNode::setPlaceholder('custom', ['string' => 'foo']);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foo" is not allowed for path "cookie_samesite". Permissible values: "lax", "strict", "none"');
        $node->finalize('custom');
    }
}
