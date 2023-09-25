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
use Symfony\Component\ObjectMapper\Exception\RuntimeException;
use Symfony\Component\ObjectMapper\Metadata\ReflectionMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Recursive;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RecursiveDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Relation;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RelationDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\TargetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\User;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\UserProfile;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallback\A as InstanceCallbackA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallback\B as InstanceCallbackB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\AToBMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\MapStructMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Source;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Target;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\A as MultipleTargetsA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\C as MultipleTargetsC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\AB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\Dto;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class ObjectMapperTest extends TestCase
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

        yield [new MultipleTargetsC(foo: 'bar'), [new MultipleTargetsA()]];
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

    public function testMapStruct()
    {
        $a = new Source('a', 'b', 'c');
        $metadata = new MapStructMapperMetadataFactory(AToBMapper::class);
        $mapper = new ObjectMapper($metadata);
        $aToBMapper = new AToBMapper($mapper);
        $b = $aToBMapper->map($a);
        $this->assertInstanceOf(Target::class, $b);
        $this->assertSame($b->propertyD, 'a');
        $this->assertSame($b->propertyC, 'c');
    }

    public function testMultipleMapProperty()
    {
        $u = new User(email: 'hello@example.com', profile: new UserProfile(firstName: 'soyuka', lastName: 'arakusa'));
        $mapper = new ObjectMapper();
        $b = $mapper->map($u);
        $this->assertInstanceOf(TargetUser::class, $b);
        $this->assertSame($b->firstName, 'soyuka');
        $this->assertSame($b->lastName, 'arakusa');
    }
}
