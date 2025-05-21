<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Symfony\Component\ObjectMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Attribute\MapCollection;
use Symfony\Component\ObjectMapper\Attribute\MapTree;

class ObjectMapperCollectionTreeTest extends TestCase
{
    public function testMapCollectionOfObjects(): void
    {
        $mapper = new ObjectMapper();
        $source = new SourceWithCollection([
            new SourceItem('A'),
            new SourceItem('B'),
        ]);

        $result = $mapper->map($source, TargetWithCollection::class);

        $this->assertInstanceOf(TargetWithCollection::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(TargetItem::class, $result->items[0]);
        $this->assertInstanceOf(TargetItem::class, $result->items[1]);
        $this->assertEquals('A', $result->items[0]->label);
        $this->assertEquals('B', $result->items[1]->label);
    }

    public function testMapTreeStructure(): void
    {
        $source = new SourceNode('Root', [
            new SourceNode('Child 1'),
            new SourceNode('Child 2', [new SourceNode('Grandchild 1')]),
        ]);

        $mapper = new ObjectMapper();
        $result = $mapper->map($source, TargetNode::class);

        var_dump($result); // Debug pour voir le résultat du mapping

        $this->assertInstanceOf(TargetNode::class, $result);
        $this->assertEquals('Root', $result->name);
        $this->assertCount(2, $result->children);

        $this->assertEquals('Child 1', $result->children[0]->name);
        $this->assertCount(0, $result->children[0]->children);

        $this->assertEquals('Child 2', $result->children[1]->name);
        $this->assertCount(1, $result->children[1]->children);
        $this->assertEquals('Grandchild 1', $result->children[1]->children[0]->name);
    }
}

class SourceItem
{
    #[Map(to: 'label')]
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

class TargetItem
{
    public function __construct(public string $label) {}
}

class SourceWithCollection
{
    #[MapCollection(of: TargetItem::class)]
    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }
}

class TargetWithCollection
{
    public array $items = [];
}

class SourceNode
{
    #[MapTree(of: TargetNode::class, childrenProperty: 'children')]
    public array $children = [];

    public function __construct(
        #[Map]
        public string $name,
        array $children = []
    ) {
        $this->children = $children;
    }
}

class TargetNode
{
    public array $children = [];

    public function __construct(public string $name = '', array $children = [])
    {
        $this->children = $children;
    }
}
