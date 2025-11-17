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
use Symfony\Component\Console\Helper\TreeHelper;
use Symfony\Component\Console\Helper\TreeNode;
use Symfony\Component\Console\Helper\TreeStyle;
use Symfony\Component\Console\Output\BufferedOutput;

class TreeHelperTest extends TestCase
{
    public function testRenderWithoutNode()
    {
        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output);

        $tree->render();
        $this->assertSame(\PHP_EOL, $output->fetch());
    }

    public function testRenderSingleNode()
    {
        $rootNode = new TreeNode('Root');
        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame("Root\n", self::normalizeLineBreaks($output->fetch()));
    }

    public function testRenderTwoLevelTree()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');

        $rootNode->addChild($child1);
        $rootNode->addChild($child2);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1
            └── Child 2
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderThreeLevelTree()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');
        $subChild1 = new TreeNode('SubChild 1');

        $child1->addChild($subChild1);
        $rootNode->addChild($child1);
        $rootNode->addChild($child2);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1
            │   └── SubChild 1
            └── Child 2
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderMultiLevelTree()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');
        $subChild1 = new TreeNode('SubChild 1');
        $subChild2 = new TreeNode('SubChild 2');
        $subSubChild1 = new TreeNode('SubSubChild 1');

        $subChild1->addChild($subSubChild1);
        $child1->addChild($subChild1);
        $child1->addChild($subChild2);
        $rootNode->addChild($child1);
        $rootNode->addChild($child2);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1
            │   ├── SubChild 1
            │   │   └── SubSubChild 1
            │   └── SubChild 2
            └── Child 2
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderSingleNodeTree()
    {
        $rootNode = new TreeNode('Root');
        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderEmptyTree()
    {
        $rootNode = new TreeNode('Root');
        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderDeeplyNestedTree()
    {
        $rootNode = new TreeNode('Root');
        $current = $rootNode;
        for ($i = 1; $i <= 10; ++$i) {
            $child = new TreeNode("Level $i");
            $current->addChild($child);
            $current = $child;
        }

        $style = new TreeStyle(...[
            '└── ',
            '└── ',
            '',
            '   ',
            '  ',
            '',
        ]);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode, [], $style);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            └── Level 1
              └── Level 2
                └── Level 3
                  └── Level 4
                    └── Level 5
                      └── Level 6
                        └── Level 7
                          └── Level 8
                            └── Level 9
                              └── Level 10
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderNodeWithMultipleChildren()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');
        $child3 = new TreeNode('Child 3');

        $rootNode->addChild($child1);
        $rootNode->addChild($child2);
        $rootNode->addChild($child3);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1
            ├── Child 2
            └── Child 3
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderNodeWithMultipleChildrenWithStringConversion()
    {
        $rootNode = new TreeNode('Root');

        $rootNode->addChild('Child 1');
        $rootNode->addChild('Child 2');
        $rootNode->addChild('Child 3');

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1
            ├── Child 2
            └── Child 3
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderTreeWithDuplicateNodeNames()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child');
        $child2 = new TreeNode('Child');
        $subChild1 = new TreeNode('Child');

        $child1->addChild($subChild1);
        $rootNode->addChild($child1);
        $rootNode->addChild($child2);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child
            │   └── Child
            └── Child
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderTreeWithComplexNodeNames()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1 (special)');
        $child2 = new TreeNode('Child_2@#$');
        $subChild1 = new TreeNode('Node with spaces');

        $child1->addChild($subChild1);
        $rootNode->addChild($child1);
        $rootNode->addChild($child2);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $tree->render();
        $this->assertSame(<<<TREE
            Root
            ├── Child 1 (special)
            │   └── Node with spaces
            └── Child_2@#$
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testRenderTreeWithCycle()
    {
        $rootNode = new TreeNode('Root');
        $child1 = new TreeNode('Child 1');
        $child2 = new TreeNode('Child 2');

        $child1->addChild($child2);
        // Create a cycle voluntarily
        $child2->addChild($child1);

        $rootNode->addChild($child1);

        $output = new BufferedOutput();
        $tree = TreeHelper::createTree($output, $rootNode);

        $this->expectException(\LogicException::class);
        $tree->render();
    }

    public function testRenderWideTree()
    {
        $rootNode = new TreeNode('Root');
        for ($i = 1; $i <= 100; ++$i) {
            $rootNode->addChild(new TreeNode("Child $i"));
        }

        $output = new BufferedOutput();

        $tree = TreeHelper::createTree($output, $rootNode);
        $tree->render();

        $lines = explode("\n", self::normalizeLineBreaks(trim($output->fetch())));
        $this->assertCount(101, $lines);
        $this->assertSame('Root', $lines[0]);
        $this->assertSame('└── Child 100', end($lines));
    }

    public function testCreateWithRoot()
    {
        $output = new BufferedOutput();
        $array = ['child1', 'child2'];

        $tree = TreeHelper::createTree($output, 'root', $array);

        $tree->render();
        $this->assertSame(<<<TREE
            root
            ├── child1
            └── child2
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testCreateWithNestedArray()
    {
        $output = new BufferedOutput();
        $array = ['child1', 'child2' => ['child2.1', 'child2.2' => ['child2.2.1']], 'child3'];

        $tree = TreeHelper::createTree($output, 'root', $array);

        $tree->render();
        $this->assertSame(<<<TREE
            root
            ├── child1
            ├── child2
            │   ├── child2.1
            │   └── child2.2
            │      └── child2.2.1
            └── child3
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testCreateWithoutRoot()
    {
        $output = new BufferedOutput();
        $array = ['child1', 'child2'];

        $tree = TreeHelper::createTree($output, null, $array);

        $tree->render();
        $this->assertSame(<<<TREE
            ├── child1
            └── child2
            TREE,
            self::normalizeLineBreaks(trim($output->fetch()))
        );
    }

    public function testCreateWithEmptyArray()
    {
        $output = new BufferedOutput();
        $array = [];

        $tree = TreeHelper::createTree($output, null, $array);

        $tree->render();
        $this->assertSame('', self::normalizeLineBreaks(trim($output->fetch())));
    }

    private static function normalizeLineBreaks($text)
    {
        return str_replace(\PHP_EOL, "\n", $text);
    }
}
