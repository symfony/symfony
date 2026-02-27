<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\DataModel\Write;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\DataModel\Write\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

class CompositeNodeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" expects at least 2 nodes.', CompositeNode::class));

        new CompositeNode('$data', [new ScalarNode('$data', Type::int())]);
    }

    public function testCannotCreateWithCompositeNodeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Cannot set "%s" as a "%s" node.', CompositeNode::class, CompositeNode::class));

        new CompositeNode('$data', [
            new CompositeNode('$data', [
                new ScalarNode('$data', Type::int()),
                new ScalarNode('$data', Type::int()),
            ]),
            new ScalarNode('$data', Type::int()),
        ]);
    }

    public function testSortNodesOnCreation()
    {
        $composite = new CompositeNode('$data', [
            $scalar = new ScalarNode('$data', Type::int()),
            $object = new ObjectNode('$data', Type::object(self::class), []),
            $collection = new CollectionNode('$data', Type::list(), new ScalarNode('$data', Type::int()), new ScalarNode('$key', Type::string())),
        ]);

        $this->assertSame([$collection, $object, $scalar], $composite->getNodes());
    }

    public function testWithAccessor()
    {
        $composite = new CompositeNode('$data', [
            new ScalarNode('$foo', Type::int()),
            new ScalarNode('$bar', Type::int()),
        ]);
        $composite = $composite->withAccessor('$baz');

        foreach ($composite->getNodes() as $node) {
            $this->assertSame('$baz', $node->getAccessor());
        }
    }
}
