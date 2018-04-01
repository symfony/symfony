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
use Symphony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Component\Config\Definition\Builder\ScalarNodeDefinition;

class NodeDefinitionTest extends TestCase
{
    public function testDefaultPathSeparatorIsDot()
    {
        $node = $this->getMockForAbstractClass(NodeDefinition::class, array('foo'));

        $this->assertAttributeSame('.', 'pathSeparator', $node);
    }

    public function testSetPathSeparatorChangesChildren()
    {
        $node = new ArrayNodeDefinition('foo');
        $scalar = new ScalarNodeDefinition('bar');
        $node->append($scalar);

        $node->setPathSeparator('/');

        $this->assertAttributeSame('/', 'pathSeparator', $node);
        $this->assertAttributeSame('/', 'pathSeparator', $scalar);
    }
}
