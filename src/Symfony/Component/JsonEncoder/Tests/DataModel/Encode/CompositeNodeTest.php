<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DataModel\Encode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\DataModel\Encode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\VariableDataAccessor;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

class CompositeNodeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeNode(new VariableDataAccessor('data'), [new ScalarNode(new VariableDataAccessor('data'), Type::int())]);
    }

    public function testCannotCreateWithCompositeNodeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeNode(new VariableDataAccessor('data'), [
            new CompositeNode(new VariableDataAccessor('data'), [
                new ScalarNode(new VariableDataAccessor('data'), Type::int()),
                new ScalarNode(new VariableDataAccessor('data'), Type::int()),
            ]),
            new ScalarNode(new VariableDataAccessor('data'), Type::int()),
        ]);
    }

    public function testSortNodesOnCreation()
    {
        $composite = new CompositeNode(new VariableDataAccessor('data'), [
            $scalar = new ScalarNode(new VariableDataAccessor('data'), Type::int()),
            $object = new ObjectNode(new VariableDataAccessor('data'), Type::object(self::class), [], false),
            $collection = new CollectionNode(new VariableDataAccessor('data'), Type::list(), new ScalarNode(new VariableDataAccessor('data'), Type::int())),
        ]);

        $this->assertSame([$collection, $object, $scalar], $composite->nodes);
    }
}
