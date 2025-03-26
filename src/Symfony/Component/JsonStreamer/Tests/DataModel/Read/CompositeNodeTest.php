<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\DataModel\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\DataModel\Read\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Read\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Read\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Read\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

class CompositeNodeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" expects at least 2 nodes.', CompositeNode::class));

        new CompositeNode([new ScalarNode(Type::int())]);
    }

    public function testCannotCreateWithCompositeNodeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Cannot set "%s" as a "%s" node.', CompositeNode::class, CompositeNode::class));

        new CompositeNode([
            new CompositeNode([
                new ScalarNode(Type::int()),
                new ScalarNode(Type::int()),
            ]),
            new ScalarNode(Type::int()),
        ]);
    }

    public function testSortNodesOnCreation()
    {
        $composite = new CompositeNode([
            $scalar = new ScalarNode(Type::int()),
            $object = new ObjectNode(Type::object(self::class), [], false),
            $collection = new CollectionNode(Type::list(), new ScalarNode(Type::int())),
        ]);

        $this->assertSame([$collection, $object, $scalar], $composite->getNodes());
    }
}
