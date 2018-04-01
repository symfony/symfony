<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Config\Definition\Builder\BooleanNodeDefinition;

class BooleanNodeDefinitionTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\Config\Definition\Exception\InvalidDefinitionException
     * @expectedExceptionMessage ->cannotBeEmpty() is not applicable to BooleanNodeDefinition.
     */
    public function testCannotBeEmptyThrowsAnException()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->cannotBeEmpty();
    }

    public function testSetDeprecated()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->setDeprecated('The "%path%" node is deprecated.');

        $node = $def->getNode();

        $this->assertTrue($node->isDeprecated());
        $this->assertSame('The "foo" node is deprecated.', $node->getDeprecationMessage($node->getName(), $node->getPath()));
    }
}
