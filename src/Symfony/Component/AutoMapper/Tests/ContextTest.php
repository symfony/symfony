<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Context;
use Symfony\Component\AutoMapper\Exception\CircularReferenceException;
use Symfony\Component\AutoMapper\Exception\NoConstructorArgumentFoundException;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class ContextTest extends TestCase
{
    public function testIsAllowedAttribute(): void
    {
        $context = new Context(null, ['id', 'age']);
        self::assertTrue($context->isAllowedAttribute('id'));
        self::assertFalse($context->isAllowedAttribute('name'));
        self::assertTrue($context->isAllowedAttribute('age'));

        $context = new Context(null, null, ['name']);
        self::assertTrue($context->isAllowedAttribute('id'));
        self::assertFalse($context->isAllowedAttribute('name'));
        self::assertTrue($context->isAllowedAttribute('age'));

        $context = new Context(null, ['id', 'age'], ['age']);
        self::assertTrue($context->isAllowedAttribute('id'));
        self::assertFalse($context->isAllowedAttribute('name'));
        self::assertFalse($context->isAllowedAttribute('age'));
    }

    public function testCircularReferenceLimit(): void
    {
        // with no circularReferenceLimit
        $object = new \stdClass();
        $context = new Context();
        $subContext = $context->withReference('reference', $object);
        self::assertTrue($subContext->shouldHandleCircularReference('reference'));

        // with circularReferenceLimit
        $object = new \stdClass();
        $context = new Context();
        $context->setCircularReferenceLimit(3);
        $subContext = $context->withReference('reference', $object);

        for ($i = 0; $i <= 2; ++$i) {
            if (2 === $i) {
                self::assertTrue($subContext->shouldHandleCircularReference('reference'));
                break;
            }

            self::assertFalse($subContext->shouldHandleCircularReference('reference'));

            // fake handleCircularReference to increment countReferenceRegistry
            $subContext->handleCircularReference('reference', $object);
        }

        self::expectException(CircularReferenceException::class);
        self::expectExceptionMessage('A circular reference has been detected when mapping the object of type "stdClass" (configured limit: 3)');
        $subContext->handleCircularReference('reference', $object);
    }

    public function testCircularReferenceHandler(): void
    {
        $object = new \stdClass();
        $context = new Context();
        $context->setCircularReferenceHandler(function ($object) {
            return $object;
        });
        $subContext = $context->withReference('reference', $object);
        self::assertTrue($subContext->shouldHandleCircularReference('reference'));
        self::assertEquals($object, $context->handleCircularReference('reference', $object));
    }

    public function testConstructorArgument(): void
    {
        $context = new Context();
        $context->setConstructorArgument(Fixtures\User::class, 'id', 10);
        $context->setConstructorArgument(Fixtures\User::class, 'age', 50);

        self::assertTrue($context->hasConstructorArgument(Fixtures\User::class, 'id'));
        self::assertFalse($context->hasConstructorArgument(Fixtures\User::class, 'name'));
        self::assertTrue($context->hasConstructorArgument(Fixtures\User::class, 'age'));

        self::assertEquals(10, $context->getConstructorArgument(Fixtures\User::class, 'id'));
        self::assertEquals(50, $context->getConstructorArgument(Fixtures\User::class, 'age'));

        self::expectException(NoConstructorArgumentFoundException::class);
        $context->getConstructorArgument(Fixtures\User::class, 'name');
    }

    public function testGroups(): void
    {
        $expected = ['group1', 'group4'];
        $context = new Context($expected);

        self::assertEquals($expected, $context->getGroups());
        self::assertTrue(\in_array('group1', $context->getGroups()));
        self::assertFalse(\in_array('group2', $context->getGroups()));
    }
}
