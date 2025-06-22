<?php

namespace Symfony\Component\ObjectMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\Exception\MapMultipleAggregateException;
use Symfony\Component\ObjectMapper\MapMultiple;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;

final class MapMultipleTest extends TestCase
{
    public function testMappingSucceeds()
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

        $mapper = new MapMultiple(new ObjectMapper());

        $target = iterator_to_array($mapper->yieldMappedObjects($source, D::class));

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

    public function testMappingSecondSourceObjectThrows()
    {
        $sourceCollection = [
            new class('value1', 'value2') {
                public function __construct(
                    public string $baz,
                    public string $bat
                ) {
                }
            },
            new class(/* Nothing to map */) {}
        ];

        $mapper = new MapMultiple(new ObjectMapper());

        $targetCollection = [];

        try {
            foreach ($mapper->yieldMappedObjects($sourceCollection, D::class) as $targetObject) {
                $targetCollection[] = $targetObject;
            }

            self::fail('Mapping second object should have thrown!');
        } catch (MapMultipleAggregateException $ex) {
            self::assertSame('Mapping source collection has failed.', $ex->getMessage());
            self::assertCount(1, $ex->innerExceptions);
            self::assertSame('Undefined property: class@anonymous::$baz', $ex->innerExceptions[0]->getMessage());
        }

        self::assertCount(1, $targetCollection, 'Mapping first object should have passed!');
        self::assertInstanceOf(D::class, $targetCollection[0]);
        /** @var D $firstTarget */
        $firstTarget = $targetCollection[0];
        self::assertSame('value1', $firstTarget->baz);
        self::assertSame('value2', $firstTarget->bat);
    }
}
