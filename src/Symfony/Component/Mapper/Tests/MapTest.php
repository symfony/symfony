<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mapper\Exception\RuntimeException;
use Symfony\Component\Mapper\ObjectMapper;
use Symfony\Component\Mapper\ReflectionMapperMetadataFactory;
use Symfony\Component\Mapper\Tests\Fixtures\A;
use Symfony\Component\Mapper\Tests\Fixtures\B;
use Symfony\Component\Mapper\Tests\Fixtures\C;
use Symfony\Component\Mapper\Tests\Fixtures\D;
use Symfony\Component\Mapper\Tests\Fixtures\DeeperRecursion\Recursive;
use Symfony\Component\Mapper\Tests\Fixtures\DeeperRecursion\RecursiveDto;
use Symfony\Component\Mapper\Tests\Fixtures\DeeperRecursion\Relation;
use Symfony\Component\Mapper\Tests\Fixtures\DeeperRecursion\RelationDto;
use Symfony\Component\Mapper\Tests\Fixtures\InstanceCallback\A as InstanceCallbackA;
use Symfony\Component\Mapper\Tests\Fixtures\InstanceCallback\B as InstanceCallbackB;
use Symfony\Component\Mapper\Tests\Fixtures\MultipleTargets\A as MultipleTargetsA;
use Symfony\Component\Mapper\Tests\Fixtures\MultipleTargets\C as MultipleTargetsC;
use Symfony\Component\Mapper\Tests\Fixtures\Recursion\AB;
use Symfony\Component\Mapper\Tests\Fixtures\Recursion\Dto;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class MapTest extends TestCase
{
    /**
     * @dataProvider mapProvider
     */
    public function testMap($expect, $args, array $deps = [])
    {
        $mapper = new ObjectMapper(...$deps);
        $this->assertEquals($expect, $mapper->map(...$args));
    }

    /**
     * @return array{expect: object, args: array, deps: array}
     */
    public function mapProvider()
    {
        $d = new D(baz: 'foo', bat: 'bar');
        $c = new C(foo: 'foo', bar: 'bar');
        $a = new A();
        $a->foo = 'test';
        $a->transform = 'test';
        $a->baz = 'me';
        $a->notinb = 'test';
        $a->relation = $c;
        $a->relationNotMapped = $d;

        $b = new B('test');
        $b->transform = 'TEST';
        $b->baz = 'me';
        $b->nomap = true;
        $b->concat = 'testme';
        $b->relation = $d;
        $b->relationNotMapped = $d;
        yield [$b, [$a]];

        $c = clone $b;
        $c->id = 1;
        yield [$c, [$a, $c]];

        $d = clone $b;
        // with propertyAccessor we call the getter
        $d->concat = 'shouldtestme';

        yield [$d, [$a], [new ReflectionMapperMetadataFactory(), PropertyAccess::createPropertyAccessor()]];

        $e = clone $b;
        $e->transform = 'Test';
        $serviceLocator = $this->createMock(ContainerInterface::class);
        $serviceLocator->method('has')->willReturnCallback(function ($v): bool {
            return 'strtoupper' === $v;
        });
        $serviceLocator->method('get')->with('strtoupper')->willReturn(fn ($v) => ucfirst($v));

        yield [$e, [$a], [new ReflectionMapperMetadataFactory(), null, $serviceLocator]];

        yield [new MultipleTargetsC(), [new MultipleTargetsA()]];
    }

    public function testHasNothingToMapTo()
    {
        $this->expectException(RuntimeException::class);
        (new ObjectMapper())->map(new class() {});
    }

    public function testRecursion()
    {
        $ab = new AB();
        $ab->ab = $ab;
        $mapper = new ObjectMapper();
        $mapped = $mapper->map($ab);
        $this->assertInstanceOf(Dto::class, $mapped);
        $this->assertSame($mapped, $mapped->dto);
    }

    public function testDeeperRecursion()
    {
        $recursive = new Recursive();
        $recursive->name = 'hi';
        $recursive->relation = new Relation();
        $recursive->relation->recursion = $recursive;
        $mapper = new ObjectMapper();
        $mapped = $mapper->map($recursive);
        $this->assertSame($mapped->relation->recursion, $mapped);
        $this->assertInstanceOf(RecursiveDto::class, $mapped);
        $this->assertInstanceOf(RelationDto::class, $mapped->relation);
    }

    public function testMapToWithInstanceHook()
    {
        $a = new InstanceCallbackA();
        $mapper = new ObjectMapper();
        $b = $mapper->map($a, InstanceCallbackB::class);
        $this->assertInstanceOf(InstanceCallbackB::class, $b);
        $this->assertSame($b->getId(), 1);
        $this->assertSame($b->name, 'test');
    }
}
