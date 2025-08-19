<?php

namespace Symfony\Component\ObjectMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\CollectionMapper;
use Symfony\Component\ObjectMapper\CollectionMapperThrowPolicy;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\WrappedMappingException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;

final class CollectionMapperTest extends TestCase
{
    public function testMapSucceeds()
    {
        $source = [
            new C(
                foo: 'foo1',
                bar: 'bar1'
            ),
            new C(
                foo: 'foo2',
                bar: 'bar2'
            ),
        ];

        $mapper = new CollectionMapper(new ObjectMapper());

        $target = iterator_to_array($mapper->map($source, D::class));

        self::assertIsArray($target);
        self::assertCount(2, $target);

        self::assertInstanceOf(D::class, $target[0]);
        /** @var D $firstTarget */
        $firstTarget = $target[0];
        self::assertSame('foo1', $firstTarget->baz);
        self::assertSame('bar1', $firstTarget->bat);

        self::assertInstanceOf(D::class, $target[1]);
        /** @var D $secondTarget */
        $secondTarget = $target[1];
        self::assertSame('foo2', $secondTarget->baz);
        self::assertSame('bar2', $secondTarget->bat);
    }

    public function testMapFailSafe()
    {
        $sourceCollection = self::createSourceCollection();

        $mapper = new CollectionMapper(new ObjectMapper(), CollectionMapperThrowPolicy::FAIL_SAFE);

        $targetCollection = [];

        try {
            foreach ($mapper->map($sourceCollection, D::class) as $targetObject) {
                $targetCollection[] = $targetObject;
            }

            self::fail('Mapping second object should have thrown!');
        } catch (WrappedMappingException $ex) {
            self::assertSame('Mapping source collection has failed. See property "exceptions" for details.', $ex->getMessage());
            self::assertCount(1, $ex->exceptions);
            self::assertSame('The property "baz" does not exist on "class@anonymous".', $ex->exceptions[0]->getMessage());
        }

        self::assertCount(2, $targetCollection, '2 mappings should have been successful!');
        self::assertInstanceOf(D::class, $targetCollection[0]);
        self::assertInstanceOf(D::class, $targetCollection[1]);

        /** @var D $firstTarget */
        $firstTarget = $targetCollection[0];
        self::assertSame('value1', $firstTarget->baz);
        self::assertSame('value2', $firstTarget->bat);

        /** @var D $secondTarget */
        $secondTarget = $targetCollection[1];
        self::assertSame('value3', $secondTarget->baz);
        self::assertSame('value4', $secondTarget->bat);
    }

    public function testMapFailEarly()
    {
        $sourceCollection = self::createSourceCollection();

        $mapper = new CollectionMapper(new ObjectMapper(), CollectionMapperThrowPolicy::FAIL_EARLY);

        $targetCollection = [];

        try {
            foreach ($mapper->map($sourceCollection, D::class) as $targetObject) {
                $targetCollection[] = $targetObject;
            }

            self::fail('Mapping should have thrown!');
        } catch (MappingException $ex) {
            self::assertSame('The property "baz" does not exist on "class@anonymous".', $ex->getMessage());
        }

        self::assertCount(1, $targetCollection, 'Only the first mapping should have been successful!');
        self::assertInstanceOf(D::class, $targetCollection[0]);

        /** @var D $firstTarget */
        $firstTarget = $targetCollection[0];
        self::assertSame('value1', $firstTarget->baz);
        self::assertSame('value2', $firstTarget->bat);
    }

    public function testMapIgnoreErrors()
    {
        $sourceCollection = self::createSourceCollection();

        $mapper = new CollectionMapper(new ObjectMapper(), CollectionMapperThrowPolicy::IGNORE_MAPPING_ERRORS);

        $targetCollection = iterator_to_array(
            $mapper->map($sourceCollection, D::class)
        );

        self::assertCount(2, $targetCollection, '2 mappings should have been successful!');
        self::assertInstanceOf(D::class, $targetCollection[0]);
        self::assertInstanceOf(D::class, $targetCollection[1]);

        /** @var D $firstTarget */
        $firstTarget = $targetCollection[0];
        self::assertSame('value1', $firstTarget->baz);
        self::assertSame('value2', $firstTarget->bat);

        /** @var D $secondTarget */
        $secondTarget = $targetCollection[1];
        self::assertSame('value3', $secondTarget->baz);
        self::assertSame('value4', $secondTarget->bat);
    }

    public function testMapIntoExistingCollection()
    {
        $sourceCollection = [ 
            new class('value1', 'value2') {
                public function __construct(public string $baz, public string $bat) {}
            },
            new class('value3', 'value4') {
                public function __construct(public string $baz, public string $bat) {}
            }
        ];

        $targetCollection = [ 
            new class('value5', 'value6') {
                public function __construct(public string $baz, public string $bat) {}
            },
            new class('value7', 'value8') {
                public function __construct(public string $baz, public string $bat) {}
            }
        ];

        $mapper = new CollectionMapper(new ObjectMapper());

        $result = iterator_to_array($mapper->map($sourceCollection, $targetCollection));

        self::assertCount(2, $targetCollection);

        $first = $targetCollection[0];
        self::assertSame('value1', $first->baz);
        self::assertSame('value2', $first->bat);

        $second = $targetCollection[1];
        self::assertSame('value3', $second->baz);
        self::assertSame('value4', $second->bat);

        self::assertCount(2, $result);
        self::assertSame($first, $result[0]);
        self::assertSame($second, $result[1]);
    }

    /**
     * Create a source collection where the 2nd object fails to be mapped.
     */
    private static function createSourceCollection(): array
    {
        return [
            new class('value1', 'value2') {
                public function __construct(
                    public string $baz,
                    public string $bat
                ) {
                }
            },
            new class(/* Nothing to map produces an exception */) {},
            new class('value3', 'value4') {
                public function __construct(
                    public string $baz,
                    public string $bat
                ) {
                }
            }
        ];
    }
}
