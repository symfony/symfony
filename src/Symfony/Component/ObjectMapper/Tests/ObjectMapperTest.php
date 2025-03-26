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
use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Recursive;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RecursiveDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Relation;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RelationDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\TargetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\User;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\UserProfile;
use Symfony\Component\ObjectMapper\Tests\Fixtures\HydrateObject\SourceOnly;
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
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\A as ServiceLocatorA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B as ServiceLocatorB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\ConditionCallable;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\TransformCallable;
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
     * @return iterable<array{0: object, 1: array, 2: array}>
     */
    public static function mapProvider(): iterable
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

        yield [$d, [$a], [new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor()]];

        yield [new MultipleTargetsC(foo: 'bar'), [new MultipleTargetsA()]];
    }

    public function testHasNothingToMapTo()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Mapping target not found for source "class@anonymous".');
        (new ObjectMapper())->map(new class {});
    }

    public function testHasNothingToMapToWithNamedClass()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Mapping target not found for source "%s".', ClassWithoutTarget::class));
        (new ObjectMapper())->map(new ClassWithoutTarget());
    }

    public function testTargetNotFound()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Mapping target class "InexistantClass" does not exist for source "%s".', ClassWithoutTarget::class));
        (new ObjectMapper())->map(new ClassWithoutTarget(), 'InexistantClass');
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

    public function testServiceLocator()
    {
        $a = new ServiceLocatorA();
        $a->foo = 'nok';

        $mapper = new ObjectMapper(
            conditionCallableLocator: $this->getServiceLocator([ConditionCallable::class => new ConditionCallable()]),
            transformCallableLocator: $this->getServiceLocator([TransformCallable::class => new TransformCallable()])
        );

        $b = $mapper->map($a);
        $this->assertSame($b->bar, 'notmapped');
        $this->assertInstanceOf(ServiceLocatorB::class, $b);

        $a->foo = 'ok';
        $b = $mapper->map($a);
        $this->assertInstanceOf(ServiceLocatorB::class, $b);
        $this->assertSame($b->bar, 'transformedok');
    }

    protected function getServiceLocator(array $factories): ContainerInterface
    {
        return new class ($factories) implements ContainerInterface {
            public function __construct(private array $factories)
            {
            }

            public function has(string $id): bool
            {
                return isset($this->factories[$id]);
            }

            public function get(string $id): mixed
            {
                return $this->factories[$id];
            }
        };
    }

    public function testSourceOnly()
    {
        $a = new \stdClass();
        $a->name = 'test';
        $mapper = new ObjectMapper();
        $mapped = $mapper->map($a, SourceOnly::class);
        $this->assertInstanceOf(SourceOnly::class, $mapped);
        $this->assertSame('test', $mapped->mappedName);

        $a = new class {
            public function __get(string $key): string
            {
                return match ($key) {
                    'name' => 'test',
                    default => throw new \LogicException($key),
                };
            }
        };

        $mapped = $mapper->map($a, SourceOnly::class);
        $this->assertInstanceOf(SourceOnly::class, $mapped);
        $this->assertSame('test', $mapped->mappedName);
    }

    public function testTransformToWrongValueType()
    {
        $this->expectException(MappingTransformException::class);
        $this->expectExceptionMessage('Cannot map "stdClass" to a non-object target of type "string".');

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: \stdClass::class, transform: fn() => 'str')]);
        $mapper = new ObjectMapper($metadata);
        $mapper->map($u);
    }

    public function testTransformToWrongObject()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Expected the mapped object to be an instance of "%s" but got "stdClass".', ClassWithoutTarget::class));

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: ClassWithoutTarget::class, transform: fn () => new \stdClass())]);
        $mapper = new ObjectMapper($metadata);
        $mapper->map($u);
    }
}
