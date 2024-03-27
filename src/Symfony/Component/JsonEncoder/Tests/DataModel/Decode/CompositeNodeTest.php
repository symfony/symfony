<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DataModel\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\DataModel\Decode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\ScalarNode;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

class CompositeNodeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeNode([new ScalarNode(Type::int())]);
    }

    public function testCannotCreateWithCompositeNodeParts()
    {
        $this->expectException(InvalidArgumentException::class);
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

        $this->assertSame([$collection, $object, $scalar], $composite->nodes);
    }
}
