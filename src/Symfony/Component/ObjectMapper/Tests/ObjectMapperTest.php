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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnorePhpunitDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\CachedObjectMapper;
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
use Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultValueStdClass\TargetDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\TargetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\User;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten\UserProfile;
use Symfony\Component\ObjectMapper\Tests\Fixtures\HydrateObject\MagicMethods;
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
    private static ?string $cacheDir = null;

    public static function getCacheDir(): string
    {
        if (self::$cacheDir) {
            return self::$cacheDir;
        }

        self::$cacheDir = sys_get_temp_dir().'/symfony_object_mapper_cache_test_'.uniqid();
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0o775, true);
        }

        return self::$cacheDir;
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$cacheDir)) {
            array_map('unlink', glob(self::$cacheDir.'/*'));
            rmdir(self::$cacheDir);
        }
    }

    #[DataProvider('mapProvider')]
    public function testMap($expect, $args, ObjectMapperInterface $mapper)
    {
        $this->assertEquals($expect, $mapper->map(...$args));
    }

    /**
     * @return iterable<array{0: ObjectMapperInterface}>
     */
    public static function objectMapperProvider(): iterable
    {
        yield [new ObjectMapper()];
        yield [new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor())];
        yield [new CachedObjectMapper(self::getCacheDir())];
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
        yield [$b, [$a], new ObjectMapper()];
        yield [$b, [$a], new CachedObjectMapper(self::getCacheDir())];

        $c = clone $b;
        $c->id = 1;
        yield [$c, [$a, $c], new ObjectMapper()];
        yield [$c, [$a, $c], new CachedObjectMapper(self::getCacheDir())];

        $d = clone $b;
        // with propertyAccessor we call the getter
        $d->concat = 'shouldtestme';

        yield [$d, [$a], new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor())];
        yield [$d, [$a], new CachedObjectMapper(self::getCacheDir(), new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor())];

        yield [new MultipleTargetsC(foo: 'bar'), [new MultipleTargetsA()], new ObjectMapper()];
        yield [new MultipleTargetsC(foo: 'bar'), [new MultipleTargetsA()], new CachedObjectMapper(self::getCacheDir())];
    }

    #[DataProvider('objectMapperProvider')]
    public function testHasNothingToMapTo(ObjectMapperInterface $objectMapper)
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Mapping target not found for source "class@anonymous".');
        $objectMapper->map(new class {});
    }

    #[DataProvider('objectMapperProvider')]
    public function testHasNothingToMapToWithNamedClass(ObjectMapperInterface $objectMapper)
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Mapping target not found for source "%s".', ClassWithoutTarget::class));
        $objectMapper->map(new ClassWithoutTarget());
    }

    #[DataProvider('objectMapperProvider')]
    public function testTargetNotFound(ObjectMapperInterface $objectMapper)
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Mapping target class "InexistantClass" does not exist for source "%s".', ClassWithoutTarget::class));
        $objectMapper->map(new ClassWithoutTarget(), 'InexistantClass');
    }

    #[DataProvider('objectMapperProvider')]
    public function testRecursion(ObjectMapperInterface $objectMapper)
    {
        $ab = new AB();
        $ab->ab = $ab;
        $mapped = $objectMapper->map($ab);
        $this->assertInstanceOf(Dto::class, $mapped);
        $this->assertSame($mapped, $mapped->dto);
    }

    #[DataProvider('objectMapperProvider')]
    public function testDeeperRecursion(ObjectMapperInterface $objectMapper)
    {
        $recursive = new Recursive();
        $recursive->name = 'hi';
        $recursive->relation = new Relation();
        $recursive->relation->recursion = $recursive;
        $mapper = $objectMapper;
        $mapped = $mapper->map($recursive);
        $this->assertSame($mapped->relation->recursion, $mapped);
        $this->assertInstanceOf(RecursiveDto::class, $mapped);
        $this->assertInstanceOf(RelationDto::class, $mapped->relation);
    }

    #[DataProvider('propertyAccessorObjectMapperProvider')]
    public function testMapWithInitializedConstructor(ObjectMapperInterface $objectMapper)
    {
        $a = new InitializedConstructorA();
        $b = $objectMapper->map($a, InitializedConstructorB::class);
        $this->assertInstanceOf(InitializedConstructorB::class, $b);
        $this->assertEquals($b->tags, ['foo', 'bar']);
    }

    #[DataProvider('propertyAccessorObjectMapperProvider')]
    public function testMapReliesOnConstructorsOwnInitialization(ObjectMapperInterface $mapper)
    {
        $expected = 'bar';

        $source = new \stdClass();
        $source->bar = $expected;

        $c = $mapper->map($source, InitializedConstructorC::class);

        $this->assertInstanceOf(InitializedConstructorC::class, $c);
        $this->assertEquals($expected, $c->bar);
    }

    #[DataProvider('propertyAccessorObjectMapperProvider')]
    public function testMapConstructorArgumentsDifferFromClassFields(ObjectMapperInterface $mapper)
    {
        $expected = 'bar';

        $source = new \stdClass();
        $source->bar = $expected;

        $actual = $mapper->map($source, InitializedConstructorD::class);

        $this->assertInstanceOf(InitializedConstructorD::class, $actual);
        $this->assertStringContainsStringIgnoringCase($expected, $actual->barUpperCase);
    }

    /**
     * @return iterable<array{0: ObjectMapperInterface}>
     */
    public static function propertyAccessorObjectMapperProvider(): iterable
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        yield [new ObjectMapper(propertyAccessor: $propertyAccessor)];
        yield [new CachedObjectMapper(self::getCacheDir(), propertyAccessor: $propertyAccessor)];
    }

    #[DataProvider('objectMapperProvider')]
    public function testMapToWithInstanceHook(ObjectMapperInterface $objectMapper)
    {
        $a = new InstanceCallbackA();
        $b = $objectMapper->map($a, InstanceCallbackB::class);
        $this->assertInstanceOf(InstanceCallbackB::class, $b);
        $this->assertSame($b->getId(), 1);
        $this->assertSame($b->name, 'test');
    }

    #[DataProvider('objectMapperProvider')]
    public function testMapToWithInstanceHookWithArguments(ObjectMapperInterface $objectMapper)
    {
        $a = new InstanceCallbackWithArgumentsA();
        $b = $objectMapper->map($a);
        $this->assertInstanceOf(InstanceCallbackWithArgumentsB::class, $b);
        $this->assertSame($a, $b->transformSource);
        $this->assertInstanceOf(InstanceCallbackWithArgumentsB::class, $b->transformValue);
    }

    #[DataProvider('mapStructObjectMapperProvider')]
    public function testMapStruct(ObjectMapperInterface $objectMapper)
    {
        $a = new Source('a', 'b', 'c');
        $aToBMapper = new AToBMapper($objectMapper);
        $b = $aToBMapper->map($a);
        $this->assertInstanceOf(Target::class, $b);
        $this->assertSame($b->propertyD, 'a');
        $this->assertSame($b->propertyC, 'c');
    }

    /**
     * @return iterable<array{0: ObjectMapperInterface}>
     */
    public static function mapStructObjectMapperProvider(): iterable
    {
        $metadata = new MapStructMapperMetadataFactory(AToBMapper::class);
        yield [new ObjectMapper($metadata)];
        yield [new CachedObjectMapper(self::getCacheDir(), $metadata)];
    }

    #[DataProvider('objectMapperProvider')]
    public function testMultipleMapProperty(ObjectMapperInterface $objectMapper)
    {
        $u = new User(email: 'hello@example.com', profile: new UserProfile(firstName: 'soyuka', lastName: 'arakusa'));
        $b = $objectMapper->map($u);
        $this->assertInstanceOf(TargetUser::class, $b);
        $this->assertSame($b->firstName, 'soyuka');
        $this->assertSame($b->lastName, 'arakusa');
    }

    public function testServiceLocator()
    {
        $conditionCallableLocator = self::getServiceLocator([ConditionCallable::class => new ConditionCallable()]);
        $transformCallableLocator = self::getServiceLocator([TransformCallable::class => new TransformCallable()]);

        $objectMapper = new ObjectMapper(
            conditionCallableLocator: $conditionCallableLocator,
            transformCallableLocator: $transformCallableLocator,
        );

        $a = new ServiceLocatorA();
        $a->foo = 'nok';

        $b = $objectMapper->map($a);
        $this->assertSame($b->bar, 'notmapped');
        $this->assertInstanceOf(ServiceLocatorB::class, $b);

        $a->foo = 'ok';
        $b = $objectMapper->map($a);
        $this->assertInstanceOf(ServiceLocatorB::class, $b);
        $this->assertSame($b->bar, 'transformedok');
    }

    private static function getServiceLocator(array $factories): ContainerInterface
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

    #[DataProvider('objectMapperProvider')]
    public function testSourceOnly(ObjectMapperInterface $objectMapper)
    {
        $a = new \stdClass();
        $a->name = 'test';
        $mapped = $objectMapper->map($a, SourceOnly::class);
        $this->assertInstanceOf(SourceOnly::class, $mapped);
        $this->assertSame('test', $mapped->mappedName);
    }

    #[DataProvider('objectMapperProvider')]
    public function testSourceOnlyWithMagicMethods(ObjectMapperInterface $objectMapper)
    {
        $mapped = $objectMapper->map(new MagicMethods(), SourceOnly::class);
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

    #[DataProvider('objectMapperProvider')]
    public function testMapTargetToSource(ObjectMapperInterface $objectMapper)
    {
        $a = new MapTargetToSourceA('str');
        $b = $objectMapper->map($a, MapTargetToSourceB::class);
        $this->assertInstanceOf(MapTargetToSourceB::class, $b);
        $this->assertSame('str', $b->target);
    }

    #[DataProvider('objectMapperProvider')]
    public function testMultipleTargetMapProperty(ObjectMapperInterface $objectMapper)
    {
        $u = new MultipleTargetPropertyA();

        $b = $objectMapper->map($u, MultipleTargetPropertyB::class);
        $this->assertInstanceOf(MultipleTargetPropertyB::class, $b);
        $this->assertEquals('TEST', $b->foo);
        $c = $objectMapper->map($u, MultipleTargetPropertyC::class);
        $this->assertInstanceOf(MultipleTargetPropertyC::class, $c);
        $this->assertEquals('test', $c->bar);
        $this->assertEquals('donotmap', $c->foo);
        $this->assertEquals('foo', $c->doesNotExistInTargetB);
    }

    #[DataProvider('objectMapperProvider')]
    public function testDefaultValueStdClass(ObjectMapperInterface $objectMapper)
    {
        $this->expectException(NoSuchPropertyException::class);
        $u = new \stdClass();
        $u->id = 'abc';
        $b = $objectMapper->map($u, TargetDto::class);
    }

    #[DataProvider('configuredPropertyAccessorObjectMapperProvider')]
    public function testDefaultValueStdClassWithPropertyInfo(ObjectMapperInterface $objectMapper)
    {
        $u = new \stdClass();
        $u->id = 'abc';
        $b = $objectMapper->map($u, TargetDto::class);
        $this->assertInstanceOf(TargetDto::class, $b);
        $this->assertSame('abc', $b->id);
        $this->assertNull($b->optional);
    }

    /**
     * @return iterable<array{0: ObjectMapperInterface}>
     */
    public static function configuredPropertyAccessorObjectMapperProvider(): iterable
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
        yield [new ObjectMapper(propertyAccessor: $propertyAccessor)];
        yield [new CachedObjectMapper(self::getCacheDir(), propertyAccessor: $propertyAccessor)];
    }

    #[DataProvider('objectMapperProvider')]
    public function testUpdateObjectWithConstructorPromotedProperties(ObjectMapperInterface $objectMapper)
    {
        $a = new PromotedConstructorSource(1, 'foo');
        $b = new PromotedConstructorTarget(1, 'bar');
        $v = $objectMapper->map($a, $b);
        $this->assertSame($v->name, 'foo');
    }

    #[DataProvider('objectMapperProvider')]
    public function testUpdateMappedObjectWithAdditionalConstructorPromotedProperties(ObjectMapperInterface $objectMapper)
    {
        $a = new PromotedConstructorWithMetadataSource(3, 'foo-will-get-updated');
        $b = new PromotedConstructorWithMetadataTarget('notOnSourceButRequired', 1, 'bar');

        $v = $objectMapper->map($a, $b);

        $this->assertSame($v->name, $a->name);
        $this->assertSame($v->number, $a->number);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('objectMapperProvider')]
    public function testMapInitializesLazyObject(ObjectMapperInterface $objectMapper)
    {
        $lazy = new LazyFoo();
        $objectMapper->map($lazy, \stdClass::class);
        $this->assertTrue($lazy->isLazyObjectInitialized());
    }

    #[RequiresPhp('>=8.4')]
    #[DataProvider('objectMapperProvider')]
    public function testMapInitializesNativePhp84LazyObject(ObjectMapperInterface $objectMapper)
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
        $d = $objectMapper->map($lazyObj, MyProxy::class);
        $this->assertSame('test', $d->name);
        $this->assertTrue($initialized);
    }

    public function testDecorateObjectMapper()
    {
        $mapper = new ObjectMapper();
        $myMapper = new class($mapper) implements ObjectMapperInterface {
            private ?\SplObjectStorage $embededMap = null;

            public function __construct(private readonly ObjectMapperInterface $mapper)
            {
                $this->embededMap = new \SplObjectStorage();
            }

            public function map(object $source, object|string|null $target = null): object
            {
                if (isset($this->embededMap[$source])) {
                    $target = $this->embededMap[$source];
                }

                $mapped = $this->mapper->map($source, $target);
                $this->embededMap[$source] = $mapped;

                return $mapped;
            }
        };

        $mapper = $mapper->withObjectMapper($myMapper);

        $d = new D(baz: 'foo', bat: 'bar');
        $c = new C(foo: 'foo', bar: 'bar');
        $myNewD = $myMapper->map($c);

        $a = new A();
        $a->foo = 'test';
        $a->transform = 'test';
        $a->baz = 'me';
        $a->notinb = 'test';
        $a->relation = $c;
        $a->relationNotMapped = $d;

        $b = $mapper->map($a);
        $this->assertSame($myNewD, $b->relation);
    }

    #[DataProvider('validPartialInputProvider')]
    public function testMapPartially(PartialInput $actual, FinalInput $expected, ObjectMapperInterface $objectMapper)
    {
        $this->assertEquals($expected, $objectMapper->map($actual));
    }

    public static function validPartialInputProvider(): iterable
    {
        $o = new ObjectMapper();
        $c = new CachedObjectMapper(self::getCacheDir());

        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = 'https://updated.website.com';

        $f = new FinalInput();
        $f->uuid = $p->uuid;
        $f->website = $p->website;

        yield [$p, $f, $o];
        yield [$p, $f, $c];

        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = null;

        $f = new FinalInput();
        $f->uuid = $p->uuid;

        yield [$p, $f, $o];
        yield [$p, $f, $c];

        $p = new PartialInput();
        $p->uuid = '6a9eb6dd-c4dc-4746-bb99-f6bad716acb2';
        $p->website = 'https://updated.website.com';
        $p->email = 'updated@email.com';

        $f = new FinalInput();
        $f->uuid = $p->uuid;
        $f->website = $p->website;
        $f->email = $p->email;

        yield [$p, $f, $o];
        yield [$p, $f, $c];
    }

    #[DataProvider('objectMapperProvider')]
    public function testMapWithSourceTransform(ObjectMapperInterface $objectMapper)
    {
        $source = new SourceEntity();
        $source->name = 'test';

        $target = $objectMapper->map($source, TargetTransformTargetDto::class);

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
}
