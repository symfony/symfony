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
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

class NodeDefinitionTest extends TestCase
{
    public function testDefaultPathSeparatorIsDot()
    {
        $node = new class('foo') extends NodeDefinition
        {
            public function getPathSeparator()
            {
                return $this->pathSeparator;
            }

            protected function createNode()
            {
            }
        };

        $this->assertSame('.', $node->getPathSeparator());
    }

    public function testSetPathSeparatorChangesChildren()
    {
        $arrayNode = new class('foo') extends ArrayNodeDefinition
        {
            public function getPathSeparator()
            {
                return $this->pathSeparator;
            }
        };

        $scalarNode = new class('foo') extends ScalarNodeDefinition
        {
            public function getPathSeparator()
            {
                return $this->pathSeparator;
            }
        };

        $arrayNode->append($scalarNode);

        $arrayNode->setPathSeparator('/');

        $this->assertSame('/', $arrayNode->getPathSeparator());
        $this->assertSame('/', $scalarNode->getPathSeparator());
    }
}
