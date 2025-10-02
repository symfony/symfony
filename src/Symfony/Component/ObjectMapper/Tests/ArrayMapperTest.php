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
use Symfony\Component\ObjectMapper\ArrayMapper;
use Symfony\Component\ObjectMapper\CollectionMapperThrowPolicy;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\WrappedMappingException;
use Symfony\Component\ObjectMapper\ObjectMapper;

final class ArrayMapperTest extends TestCase
{
    public function testSuccess()
    {
        $sourceCollection = [
            new class('value1', 'value2') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
            new class('value3', 'value4') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $targetCollection = [
            new class('value5', 'value6') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
            new class('value7', 'value8') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $mapper = new ArrayMapper(new ObjectMapper(), CollectionMapperThrowPolicy::FAIL_SAFE);
        $mapper->map($sourceCollection, $targetCollection);

        $first = $targetCollection[0];
        self::assertSame('value1', $first->baz);
        self::assertSame('value2', $first->bat);

        $second = $targetCollection[1];
        self::assertSame('value3', $second->baz);
        self::assertSame('value4', $second->bat);
    }

    public function testFailSafe()
    {
        $sourceCollection = [
            new class('value1', 'value2') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
            new class('value3', 'value4') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $targetCollection = [
            new class('value5', 'value6') {
                public function __construct(public string $foo, public string $bar)
                {
                }
            },
            new class('value7', 'value8') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $mapper = new ArrayMapper(new ObjectMapper(), CollectionMapperThrowPolicy::FAIL_SAFE);

        try {
            $mapper->map($sourceCollection, $targetCollection);
            self::fail('Mapping should have thrown!');
        } catch (WrappedMappingException $ex) {
            self::assertEquals('Mapping source collection has failed. See property "exceptions" for details.', $ex->getMessage());
            self::assertCount(1, $ex->exceptions);
            self::assertEquals('The property "foo" does not exist on "class@anonymous".', $ex->exceptions[0]->getMessage());
        }

        $first = $targetCollection[0];
        self::assertSame('value5', $first->foo, 'Is not the initial value!');
        self::assertSame('value6', $first->bar, 'Is not the initial value!');

        $second = $targetCollection[1];
        self::assertSame('value3', $second->baz);
        self::assertSame('value4', $second->bat);
    }

    public function testFailEarly()
    {
        $sourceCollection = [
            new class('value1', 'value2') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
            new class('value3', 'value4') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];
        
        $targetCollection = [
            new class('value5', 'value6') {
                public function __construct(public string $foo, public string $bar)
                {
                }
            },
            new class('value7', 'value8') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $mapper = new ArrayMapper(new ObjectMapper(), CollectionMapperThrowPolicy::FAIL_EARLY);

        try {
            $mapper->map($sourceCollection, $targetCollection);
            self::fail('Mapping should have thrown!');
        } catch (MappingException $ex) {
            self::assertEquals('The property "foo" does not exist on "class@anonymous".', $ex->getMessage());
        }

        $first = $targetCollection[0];
        self::assertSame('value5', $first->foo, 'Is not the initial value!');
        self::assertSame('value6', $first->bar, 'Is not the initial value!');

        $second = $targetCollection[1];
        self::assertSame('value7', $second->baz, 'Is not the initial value!');
        self::assertSame('value8', $second->bat, 'Is not the initial value!');
    }

    public function testIgnoreErrors()
    {
        $sourceCollection = [
            new class('value1', 'value2') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
            new class('value3', 'value4') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];
        
        $targetCollection = [
            new class('value5', 'value6') {
                public function __construct(public string $foo, public string $bar)
                {
                }
            },
            new class('value7', 'value8') {
                public function __construct(public string $baz, public string $bat)
                {
                }
            },
        ];

        $mapper = new ArrayMapper(new ObjectMapper(), CollectionMapperThrowPolicy::IGNORE_MAPPING_ERRORS);
        $mapper->map($sourceCollection, $targetCollection);

        self::assertCount(2, $targetCollection);

        $first = $targetCollection[0];
        self::assertSame('value5', $first->foo, 'Is not the initial value!');
        self::assertSame('value6', $first->bar, 'Is not the initial value!');

        $second = $targetCollection[1];
        self::assertSame('value3', $second->baz);
        self::assertSame('value4', $second->bat);
    }
}
