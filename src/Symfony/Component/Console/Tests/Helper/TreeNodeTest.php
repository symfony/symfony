<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\TreeNode;

class TreeNodeTest extends TestCase
{
    public function testNodeInitialization()
    {
        $node = new TreeNode('Root');
        $this->assertSame('Root', $node->getValue());
        $this->assertSame(0, iterator_count($node->getChildren()));
    }

    public function testAddingChildren()
    {
        $root = new TreeNode('Root');
        $child = new TreeNode('Child');

        $root->addChild($child);

        $this->assertSame(1, iterator_count($root->getChildren()));
        $this->assertSame($child, iterator_to_array($root->getChildren())[0]);
    }

    public function testAddingChildrenAsString()
    {
        $root = new TreeNode('Root');

        $root->addChild('Child 1');
        $root->addChild('Child 2');

        $this->assertSame(2, iterator_count($root->getChildren()));

        $children = iterator_to_array($root->getChildren());

        $this->assertSame(0, iterator_count($children[0]->getChildren()));
        $this->assertSame(0, iterator_count($children[1]->getChildren()));

        $this->assertSame('Child 1', $children[0]->getValue());
        $this->assertSame('Child 2', $children[1]->getValue());
    }

    public function testAddingChildrenWithGenerators()
    {
        $root = new TreeNode('Root');

        $root->addChild(function () {
            yield new TreeNode('Generated Child 1');
            yield new TreeNode('Generated Child 2');
        });

        $this->assertSame(2, iterator_count($root->getChildren()));

        $children = iterator_to_array($root->getChildren());

        $this->assertSame('Generated Child 1', $children[0]->getValue());
        $this->assertSame('Generated Child 2', $children[1]->getValue());
    }

    public function testRecursiveStructure()
    {
        $root = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');
        $leaf1 = new TreeNode('Leaf 1');

        $child1->addChild($leaf1);
        $root->addChild($child1);
        $root->addChild($child2);

        $this->assertSame(2, iterator_count($root->getChildren()));
        $this->assertSame($leaf1, iterator_to_array($child1->getChildren())[0]);
    }
}
