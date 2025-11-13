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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Recursive;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RecursiveDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\Relation;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DeeperRecursion\RelationDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultLazy\OrderSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultLazy\OrderTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultLazy\UserSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultLazy\UserTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultValueStdClass\TargetDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\TargetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\User;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\UserProfile;
use Symfony\Component\ObjectMapper\Tests\Fixtures\HydrateObject\SourceOnly;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\A as InitializedConstructorA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\B as InitializedConstructorB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\C as InitializedConstructorC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor\D as InitializedConstructorD;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallback\A as InstanceCallbackA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallback\B as InstanceCallbackB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallbackWithArguments\A as InstanceCallbackWithArgumentsA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\InstanceCallbackWithArguments\B as InstanceCallbackWithArgumentsB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\LazyFoo;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\AToBMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\MapStructMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Source;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Target;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapTargetToSource\A as MapTargetToSourceA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapTargetToSource\B as MapTargetToSourceB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\A as MultipleTargetPropertyA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\B as MultipleTargetPropertyB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\C as MultipleTargetPropertyC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\A as MultipleTargetsA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\C as MultipleTargetsC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MyProxy;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput\FinalInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput\PartialInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Source as PromotedConstructorSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Target as PromotedConstructorTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructorWithMetadata\Source as PromotedConstructorWithMetadataSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructorWithMetadata\Target as PromotedConstructorWithMetadataTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\AB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\Dto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\A as ServiceLocatorA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B as ServiceLocatorB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\ConditionCallable;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\TransformCallable;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetTransform\SourceEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetTransform\TargetDto as TargetTransformTargetDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionD;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class ObjectMapperTest extends TestCase
{
    #[DataProvider('mapProvider')]
    public function testMap($expect, $args, array $deps = [])
    {
        $mapper = new ObjectMapper(...$deps);
        $mapped = $mapper->map(...$args);

        if (\PHP_VERSION_ID >= 80400 && isset($mapped->relation) && $mapped->relation instanceof D ) {
            $mapped->relation->baz;
        }

        $this->assertEquals($expect, $mapped);
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

    public function testMapWithInitializedConstructor()
    {
        $a = new InitializedConstructorA();
        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());
        $b = $mapper->map($a, InitializedConstructorB::class);
        $this->assertInstanceOf(InitializedConstructorB::class, $b);
        $this->assertEquals($b->tags, ['foo', 'bar']);
    }

    public function testMapReliesOnConstructorsOwnInitialization()
    {
        $expected = 'bar';

        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());

        $source = new \stdClass();
        $source->bar = $expected;

        $c = $mapper->map($source, InitializedConstructorC::class);

        $this->assertInstanceOf(InitializedConstructorC::class, $c);
        $this->assertEquals($expected, $c->bar);
    }

    public function testMapConstructorArgumentsDifferFromClassFields()
    {
        $expected = 'bar';

        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());

        $source = new \stdClass();
        $source->bar = $expected;

        $actual = $mapper->map($source, InitializedConstructorD::class);

        $this->assertInstanceOf(InitializedConstructorD::class, $actual);
        $this->assertStringContainsStringIgnoringCase($expected, $actual->barUpperCase);
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

    public function testMapToWithInstanceHookWithArguments()
    {
        $a = new InstanceCallbackWithArgumentsA();
        $mapper = new ObjectMapper();
        $b = $mapper->map($a);
        $this->assertInstanceOf(InstanceCallbackWithArgumentsB::class, $b);
        $this->assertSame($a, $b->transformSource);
        $this->assertInstanceOf(InstanceCallbackWithArgumentsB::class, $b->transformValue);
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
        return new class($factories) implements ContainerInterface {
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
    }

    public function testSourceOnlyWithMagicMethods()
    {
        $mapper = new ObjectMapper();
        $a = new class {
            public function __isset($key): bool
            {
                return 'name' === $key;
            }

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
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: \stdClass::class, transform: fn () => 'str')]);
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

    public function testMapTargetToSource()
    {
        $a = new MapTargetToSourceA('str');
        $mapper = new ObjectMapper();
        $b = $mapper->map($a, MapTargetToSourceB::class);
        $this->assertInstanceOf(MapTargetToSourceB::class, $b);
        $this->assertSame('str', $b->target);
    }

    public function testMultipleTargetMapProperty()
    {
        $u = new MultipleTargetPropertyA();

        $mapper = new ObjectMapper();
        $b = $mapper->map($u, MultipleTargetPropertyB::class);
        $this->assertInstanceOf(MultipleTargetPropertyB::class, $b);
        $this->assertEquals('TEST', $b->foo);
        $c = $mapper->map($u, MultipleTargetPropertyC::class);
        $this->assertInstanceOf(MultipleTargetPropertyC::class, $c);
        $this->assertEquals('test', $c->bar);
        $this->assertEquals('donotmap', $c->foo);
        $this->assertEquals('foo', $c->doesNotExistInTargetB);
    }

    public function testDefaultValueStdClass()
    {
        $this->expectException(NoSuchPropertyException::class);
        $u = new \stdClass();
        $u->id = 'abc';
        $mapper = new ObjectMapper();
        $b = $mapper->map($u, TargetDto::class);
    }

    public function testDefaultValueStdClassWithPropertyInfo()
    {
        $u = new \stdClass();
        $u->id = 'abc';
        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor());
        $b = $mapper->map($u, TargetDto::class);
        $this->assertInstanceOf(TargetDto::class, $b);
        $this->assertSame('abc', $b->id);
        $this->assertNull($b->optional);
    }

    #[DataProvider('objectMapperProvider')]
    public function testUpdateObjectWithConstructorPromotedProperties(ObjectMapperInterface $mapper)
    {
        $a = new PromotedConstructorSource(1, 'foo');
        $b = new PromotedConstructorTarget(1, 'bar');
        $v = $mapper->map($a, $b);
        $this->assertSame($v->name, 'foo');
    }

    #[DataProvider('objectMapperProvider')]
    public function testUpdateMappedObjectWithAdditionalConstructorPromotedProperties(ObjectMapperInterface $mapper)
    {
        $a = new PromotedConstructorWithMetadataSource(3, 'foo-will-get-updated');
        $b = new PromotedConstructorWithMetadataTarget('notOnSourceButRequired', 1, 'bar');

        $v = $mapper->map($a, $b);

        $this->assertSame($v->name, $a->name);
        $this->assertSame($v->number, $a->number);
    }

    /**
     * @return iterable<array{0: ObjectMapperInterface}>
     */
    public static function objectMapperProvider(): iterable
    {
        yield [new ObjectMapper()];
        yield [new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor())];
    }

    public function testMapInitializesLazyObject()
    {
        $lazy = new LazyFoo();
        $mapper = new ObjectMapper();
        $mapper->map($lazy, \stdClass::class);
        $this->assertTrue($lazy->isLazyObjectInitialized());
    }

    #[RequiresPhp('>=8.4')]
    public function testMapInitializesNativePhp84LazyObject()
    {
        $initialized = false;
        $initializer = function () use (&$initialized) {
            $initialized = true;

            $p = new MyProxy();
            $p->name = 'test';

            return $p;
        };

        $r = new \ReflectionClass(MyProxy::class);
        $lazyObj = $r->newLazyProxy($initializer);
        $this->assertFalse($initialized);
        $mapper = new ObjectMapper();
        $d = $mapper->map($lazyObj, MyProxy::class);
        $this->assertSame('test', $d->name);
        $this->assertTrue($initialized);
    }

    public function testDecorateObjectMapper()
    {
        $mapper = new ObjectMapper();
        $myMapper = new class($mapper) implements ObjectMapperInterface {
            public function __construct(private ObjectMapperInterface $mapper)
            {
                $this->mapper = $mapper->withObjectMapper($this);
            }

            public function map(object $source, object|string|null $target = null): object
            {
                $mapped = $this->mapper->map($source, $target);

                if ($source instanceof C) {
                    $mapped->baz = 'got decorated';
                }

                return $mapped;
            }
        };

        $d = new D(baz: 'foo', bat: 'bar');
        $c = new C(foo: 'foo', bar: 'bar');
        $myNewD = $myMapper->map($c);
        $this->assertSame('got decorated', $myNewD->baz);

        $a = new A();
        $a->foo = 'test';
        $a->transform = 'test';
        $a->baz = 'me';
        $a->notinb = 'test';
        $a->relation = $c;
        $a->relationNotMapped = $d;

        $b = $myMapper->map($a);
        $this->assertSame('got decorated', $b->relation->baz);
    }

    #[DataProvider('validPartialInputProvider')]
    public function testMapPartially(PartialInput $actual, FinalInput $expected)
    {
        $mapper = new ObjectMapper();
        $this->assertEquals($expected, $mapper->map($actual));
    }

    public static function validPartialInputProvider(): iterable
    {
        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = 'https://updated.website.com';

        $f = new FinalInput();
        $f->uuid = $p->uuid;
        $f->website = $p->website;

        yield [$p, $f];

        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = null;

        $f = new FinalInput();
        $f->uuid = $p->uuid;

        yield [$p, $f];

        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = 'https://updated.website.com';
        $p->email = 'updated@email.com';

        $f = new FinalInput();
        $f->uuid = $p->uuid;
        $f->website = $p->website;
        $f->email = $p->email;

        yield [$p, $f];
    }

    public function testMapWithSourceTransform()
    {
        $source = new SourceEntity();
        $source->name = 'test';

        $mapper = new ObjectMapper();
        $target = $mapper->map($source, TargetTransformTargetDto::class);

        $this->assertInstanceOf(TargetTransformTargetDto::class, $target);
        $this->assertTrue($target->transformed);
        $this->assertSame('test', $target->name);
    }

    public function testTransformCollection()
    {
        $u = new TransformCollectionA();
        $u->foo = [new TransformCollectionC('a'), new TransformCollectionC('b')];
        $mapper = new ObjectMapper();

        $transformed = $mapper->map($u, TransformCollectionB::class);

        $this->assertEquals([new TransformCollectionD('a'), new TransformCollectionD('b')], $transformed->foo);
    }

    #[RequiresPhp('>=8.4')]
    public function testEmbedsAreLazyLoadedByDefault()
    {
        $mapper = new ObjectMapper();
        $source = new OrderSource();
        $source->id = 123;
        $source->user = new UserSource();
        $source->user->name = 'Test User';
        $target = $mapper->map($source, OrderTarget::class);
        $this->assertInstanceOf(OrderTarget::class, $target);
        $this->assertSame(123, $target->id);
        $this->assertInstanceOf(UserTarget::class, $target->user);
        $refl = new \ReflectionClass(UserTarget::class);
        $this->assertTrue($refl->isUninitializedLazyObject($target->user));
        $this->assertSame('Test User', $target->user->name);
        $this->assertFalse($refl->isUninitializedLazyObject($target->user));
    }
}
