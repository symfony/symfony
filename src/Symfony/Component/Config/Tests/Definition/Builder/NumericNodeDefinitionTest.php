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
use Symfony\Component\Config\Definition\Builder\FloatNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;

class NumericNodeDefinitionTest extends TestCase
{
    public function testIncoherentMinAssertion()
    {
        $node = new IntegerNodeDefinition('foo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot define a min(4) as you already have a max(3)');

        $node->max(3)->min(4);
    }

    public function testIncoherentMaxAssertion()
    {
        $node = new IntegerNodeDefinition('foo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot define a max(2) as you already have a min(3)');

        $node->min(3)->max(2);
    }

    public function testIntegerMinAssertion()
    {
        $node = new IntegerNodeDefinition('foo');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 4 is too small for path "foo". Should be greater than or equal to 5');

        $node->min(5)->getNode()->finalize(4);
    }

    public function testIntegerMaxAssertion()
    {
        $node = new IntegerNodeDefinition('foo');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 4 is too big for path "foo". Should be less than or equal to 3');

        $node->max(3)->getNode()->finalize(4);
    }

    public function testIntegerValidMinMaxAssertion()
    {
        $node = new IntegerNodeDefinition('foo');
        $node = $node->min(3)->max(7)->getNode();
        $this->assertEquals(4, $node->finalize(4));
    }

    public function testFloatMinAssertion()
    {
        $node = new FloatNodeDefinition('foo');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 400 is too small for path "foo". Should be greater than or equal to 500');

        $node->min(5E2)->getNode()->finalize(4e2);
    }

    public function testFloatMaxAssertion()
    {
        $node = new FloatNodeDefinition('foo');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 4.3 is too big for path "foo". Should be less than or equal to 0.3');

        $node->max(0.3)->getNode()->finalize(4.3);
    }

    public function testFloatValidMinMaxAssertion()
    {
        $node = new FloatNodeDefinition('foo');
        $node = $node->min(3.0)->max(7e2)->getNode();
        $this->assertEquals(4.5, $node->finalize(4.5));
    }

    public function testCannotBeEmptyThrowsAnException()
    {
        $node = new IntegerNodeDefinition('foo');

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->cannotBeEmpty() is not applicable to NumericNodeDefinition.');

        $node->cannotBeEmpty();
    }
}
