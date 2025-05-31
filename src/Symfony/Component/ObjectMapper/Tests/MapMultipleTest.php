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
    public function testMappingSucceeds(): void
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
        $firstTarget = $target[0];
        assert($firstTarget instanceof D);
        self::assertEquals('foo1', $firstTarget->baz);
        self::assertEquals('bar1', $firstTarget->bat);

        self::assertInstanceOf(D::class, $target[1]);
        $secondTarget = $target[1];
        assert($secondTarget instanceof D);
        self::assertEquals('foo2', $secondTarget->baz);
        self::assertEquals('bar2', $secondTarget->bat);
    }

    public function testMappingSecondSourceObjectThrows(): void
    {
        $source = [
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

        $target = [];

        try {
            foreach ($mapper->yieldMappedObjects($source, D::class) as $targetObject) {
                $target[] = $targetObject;
            }

            self::fail('Mapping of second source element should have thrown!');
        } catch (MapMultipleAggregateException $ex) {
            self::assertEquals('Mapping source collection has failed.', $ex->getMessage());
            self::assertCount(1, $ex->innerExceptions);
            self::assertEquals('Undefined property: class@anonymous::$baz', $ex->innerExceptions[0]->getMessage());
        }

        self::assertCount(1, $target, 'Mapping first source collection object should have passed!');
        self::assertInstanceOf(D::class, $target[0]);
        $firstTarget = $target[0];
        assert($firstTarget instanceof D);
        self::assertEquals('value1', $firstTarget->baz);
        self::assertEquals('value2', $firstTarget->bat);
    }
}
