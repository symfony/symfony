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
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Condition\ClassRule;
use Symfony\Component\ObjectMapper\Condition\ClassRuleList;
use Symfony\Component\ObjectMapper\Exception\InvalidArgumentException;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchCallableException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReverseClassObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Amount;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\AutoNestedFlatTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\AutoNestedInnerSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\AutoNestedOtherTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\AutoNestedOuterSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\BasePaymentView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestWithSourceAndAutoMappedView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestWithSourceView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\ExtensibleTargetChild;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\ExtensibleTargetParent;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Invoice;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\InvoiceView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Payment;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\PaymentView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\PreMappedSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\PreMappedTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\PrivateMappedChildView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Quote;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\QuoteRequestView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\RefundPayment;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\RichDomainUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\RichDomainUserView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\SharedSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\SharedTargetWithoutTransform;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\SharedTargetWithTransform;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassRule\A as ClassRuleA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassRule\B as ClassRuleB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassRule\C as ClassRuleC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument\InputSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalSourceMap\Address as ConditionalSourceMapAddress;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalSourceMap\User as ConditionalSourceMapUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalSourceMap\UserDto as ConditionalSourceMapUserDto;
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
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectionlessTargetTransform\Product as DirectionlessTransformProduct;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectionlessTargetTransform\ProductInput as DirectionlessTransformProductInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EmbeddedMapping\Address;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EmbeddedMapping\User as UserEmbeddedMapping;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EmbeddedMapping\UserDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\NestedSource as ExplicitSourceNestedSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\NestedTarget as ExplicitSourceNestedTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\Source as ExplicitSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\Target as ExplicitSourceTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitTargetPriority\ChildSource as ExplicitTargetPriorityChildSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitTargetPriority\ConditionalSource as ExplicitTargetPriorityConditionalSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitTargetPriority\ReversedSource as ExplicitTargetPriorityReversedSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitTargetPriority\Source as ExplicitTargetPrioritySource;
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
use Symfony\Component\ObjectMapper\Tests\Fixtures\InvalidConfiguration;
use Symfony\Component\ObjectMapper\Tests\Fixtures\IsNotNullCondition\IsNotNullSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\IsNotNullCondition\IsNotNullSourceMapping;
use Symfony\Component\ObjectMapper\Tests\Fixtures\IsNotNullCondition\IsNotNullTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\IsNotNullCondition\IsNotNullTargetMapping;
use Symfony\Component\ObjectMapper\Tests\Fixtures\LazyFoo;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUserView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\ExistingObjectWithPublicProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\ExistingObjectWithSetter;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\NestedExistingTagTransformer;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\Post as MapExistingObjectPost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\PostDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject\Tag;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware\A as MappingAwareA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware\B as MappingAwareB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware\C as MappingAwareC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware\D as MappingAwareD;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware\MappingAwareTransformer;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\AToBMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\MapStructMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Source;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Target;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapTargetToSource\A as MapTargetToSourceA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapTargetToSource\B as MapTargetToSourceB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapTargetToSource\C as MapTargetToSourceC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleSourceProperty\A as MultipleSourcePropertyA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleSourceProperty\B as MultipleSourcePropertyB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleSourceProperty\C as MultipleSourcePropertyC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\A as MultipleTargetPropertyA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\B as MultipleTargetPropertyB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty\C as MultipleTargetPropertyC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\A as MultipleTargetsA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargets\C as MultipleTargetsC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetsWithTransform\PlainTarget as MultipleTargetsWithTransformPlainTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetsWithTransform\Source as MultipleTargetsWithTransformSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MyProxy;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedClassTransform\Dog;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedClassTransform\KennelSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedClassTransform\KennelTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping\LineItemSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping\LineItemTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping\OrderSource as NestedCollectionOrderSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping\OrderTarget as NestedCollectionOrderTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget\Inner;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget\InnerMapped;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget\Outer;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping\NestedBankDataDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping\NestedBankDataResource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping\NestedBankDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer\ChildWithClassTransformTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer\ChildWithoutClassTransformerTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer\ParentSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer\ParentTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRecursion\Source as NestedMergeRecursionSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRecursion\Target as NestedMergeRecursionTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\InnerTargetA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterSource as MultiTargetOuterSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterSourceWithRenamedProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterTarget as MultiTargetOuterTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterTargetWithInnerSourceProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterTargetWithInterfaceProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterTargetWithRenamedProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget\OuterTargetWithUntypedProperty;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedValueOfPropertyType\OuterSource as PropertyTypeOuterSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedValueOfPropertyType\OuterTarget as PropertyTypeOuterTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput\FinalInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput\PartialInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntityDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ConstructorlessChildEntityDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\MappedChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\MappedChildEntityDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\RedeclaredChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\RedeclaredChildEntityDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Source as PromotedConstructorSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructor\Target as PromotedConstructorTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructorWithMetadata\Source as PromotedConstructorWithMetadataSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructorWithMetadata\Target as PromotedConstructorWithMetadataTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty\ReadOnlyPromotedPropertyA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty\ReadOnlyPromotedPropertyAMapped;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty\ReadOnlyPromotedPropertyB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty\ReadOnlyPromotedPropertyBMapped;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\AB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion\Dto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\ChildSource as RecursionCacheChildSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\ItemSource as RecursionCacheItemSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\ItemSummaryTarget as RecursionCacheItemSummaryTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\ItemTarget as RecursionCacheItemTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\SelfSource as RecursionCacheSelfSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\SelfSummaryTarget as RecursionCacheSelfSummaryTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget\SelfTarget as RecursionCacheSelfTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SelfReferencing\Category;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SelfReferencing\CategoryDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue\LoadedValueService;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue\ServiceLoadedValueTransformer;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue\ValueToMap;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue\ValueToMapRelation;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\A as ServiceLocatorA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B as ServiceLocatorB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\ConditionCallable;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\TransformCallable;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\Lead as SourceCarriesMetadataLead;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\LeadDto as SourceCarriesMetadataLeadDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\TypeDto as SourceCarriesMetadataTypeDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SubclassTargetWithTransform\ChildTarget as SubclassTargetWithTransformChildTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SubclassTargetWithTransform\ParentTarget as SubclassTargetWithTransformParentTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SubclassTargetWithTransform\Source as SubclassTargetWithTransformSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetInClassMap\OtherView as TargetInClassMapOtherView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetInClassMap\Source as TargetInClassMapSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetInClassMap\Target as TargetInClassMapTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetTransform\SourceEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TargetTransform\TargetDto as TargetTransformTargetDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Transform\TransformToStdClass;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Transform\TransformToString;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionC;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection\TransformCollectionD;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformSourceProperty\Source as TransformSourcePropertySource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\TransformSourceProperty\Target as TransformSourcePropertyTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Uninitialized\Draft;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Uninitialized\DraftView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Uninitialized\Post;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Uninitialized\PostView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Unreadable\Order;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Unreadable\OrderView;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class ObjectMapperTest extends TestCase
{
    #[DataProvider('mapProvider')]
    public function testMap($expect, $args, array $deps = [])
    {
        $mapper = new ObjectMapper(...$deps);
        $mapped = $mapper->map(...$args);

        if (isset($mapped->relation) && $mapped->relation instanceof D) {
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

        $existingObjectWithSetterExpected = new ExistingObjectWithSetter('bar');
        $existingObjectWithSetterTarget = new ExistingObjectWithSetter('foo');
        yield [$existingObjectWithSetterExpected, [(object) ['name' => 'bar'], $existingObjectWithSetterTarget], [new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor()]];

        $existingObjectWithPublicPropertyExpected = new ExistingObjectWithPublicProperty('bar');
        $existingObjectWithPublicPropertyTarget = new ExistingObjectWithPublicProperty('foo');
        yield [$existingObjectWithPublicPropertyExpected, [(object) ['name' => 'bar'], $existingObjectWithPublicPropertyTarget], [new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor()]];

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

    public function testNestedMapCallDoesNotReconstructExistingTarget()
    {
        $existingTag = new Tag('original name');
        $this->assertSame(1, $existingTag->constructorCalls);

        $mapper = new ObjectMapper(
            transformCallableLocator: $this->getServiceLocator([
                NestedExistingTagTransformer::class => new NestedExistingTagTransformer($existingTag),
            ]),
        );

        $post = $mapper->map(new PostDto(), MapExistingObjectPost::class);

        $this->assertInstanceOf(MapExistingObjectPost::class, $post);
        $this->assertSame($existingTag, $post->tag);
        $this->assertSame('updated name', $existingTag->name);
        $this->assertSame(1, $existingTag->constructorCalls);
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
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());
        $b = $mapper->map($a, InitializedConstructorB::class);
        $this->assertInstanceOf(InitializedConstructorB::class, $b);
        $this->assertEquals($b->tags, ['foo', 'bar']);
    }

    public function testMapReliesOnConstructorsOwnInitialization()
    {
        $expected = 'bar';

        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());

        $source = new \stdClass();
        $source->bar = $expected;

        $c = $mapper->map($source, InitializedConstructorC::class);

        $this->assertInstanceOf(InitializedConstructorC::class, $c);
        $this->assertEquals($expected, $c->bar);
    }

    public function testMapConstructorArgumentsDifferFromClassFields()
    {
        $expected = 'bar';

        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());

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
        $metadata->method('create')->willReturn([new Mapping(target: \stdClass::class, transform: new TransformToString())]);
        $mapper = new ObjectMapper($metadata);
        $mapper->map($u);
    }

    public function testHasInvalidTransformValue()
    {
        $this->expectException(NoSuchCallableException::class);
        $this->expectExceptionMessage('"wrongMethod" is not a valid callable. If you use a class, make sure it implements "Symfony\Component\ObjectMapper\TransformCallableInterface".');

        (new ObjectMapper())->map(new InvalidConfiguration('foo', 'bar'));
    }

    public function testTransformToWrongObject()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Expected the mapped object to be an instance of "%s" but got "stdClass".', ClassWithoutTarget::class));

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->willReturn([new Mapping(target: ClassWithoutTarget::class, transform: new TransformToStdClass())]);
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

    public function testMapTargetToSourceIsIgnoredWhenMappingFromTheSource()
    {
        $b = new MapTargetToSourceB('str');
        $mapper = new ObjectMapper();
        $c = $mapper->map($b, MapTargetToSourceC::class);
        $this->assertInstanceOf(MapTargetToSourceC::class, $c);
        $this->assertSame('str', $c->target);
    }

    public function testExplicitSourceTakesPrecedenceOverSameNamedProperty()
    {
        $mapper = new ObjectMapper();
        $target = $mapper->map(new ExplicitSource(), ExplicitSourceTarget::class);
        $this->assertSame('from-reasonText', $target->reason);
    }

    public function testExplicitSourceSupportsPropertyPath()
    {
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());
        $target = $mapper->map(new ExplicitSourceNestedSource(), ExplicitSourceNestedTarget::class);
        $this->assertSame('from-nested-description', $target->reason);
    }

    public function testMultipleTargetMapProperty()
    {
        $u = new MultipleTargetPropertyA();

        $mapper = new ObjectMapper();
        $b = $mapper->map($u, MultipleTargetPropertyB::class);
        $this->assertInstanceOf(MultipleTargetPropertyB::class, $b);
        $this->assertEquals('TEST', $b->foo);
        $this->assertEquals('testother', $b->otherFoo);

        $c = $mapper->map($u, MultipleTargetPropertyC::class);
        $this->assertInstanceOf(MultipleTargetPropertyC::class, $c);
        $this->assertEquals('test', $c->bar);
        $this->assertEquals('donotmap', $c->foo);
        $this->assertEquals('testother', $c->otherFoo);
        $this->assertEquals('foo', $c->doesNotExistInTargetB);
    }

    public function testMultipleSourceMapProperty()
    {
        $b = new MultipleSourcePropertyB();
        $c = new MultipleSourcePropertyC();
        $mapper = new ObjectMapper();

        $a1 = $mapper->map($b, MultipleSourcePropertyA::class);
        $this->assertInstanceOf(MultipleSourcePropertyA::class, $a1);
        $this->assertEquals('test', $a1->something);
        $this->assertEquals('TEST', $a1->somethingOther);

        $a2 = $mapper->map($c, MultipleSourcePropertyA::class);
        $this->assertInstanceOf(MultipleSourcePropertyA::class, $a2);
        $this->assertEquals('TEST', $a2->something);
        $this->assertEquals('DONOTMAP', $a2->somethingOther);
        $this->assertEquals('foo', $a2->doesNotExistInSourceB);
    }

    public function testMultipleClassRuleMapProperty()
    {
        $a = new ClassRuleA();

        $mapper = new ObjectMapper();
        $b = $mapper->map($a, ClassRuleB::class);
        $this->assertInstanceOf(ClassRuleB::class, $b);
        $this->assertEquals('TESTTARGETED', $b->foo);

        $c = $mapper->map($a, ClassRuleC::class);
        $this->assertInstanceOf(ClassRuleC::class, $c);
        $this->assertEquals('testTargeted', $c->bar);
        $this->assertEquals('donotmap', $c->foo);

        $b = new ClassRuleB();
        $c = new ClassRuleC();
        $mapper = new ObjectMapper();

        $a1 = $mapper->map($b, ClassRuleA::class);
        $this->assertInstanceOf(ClassRuleA::class, $a1);
        $this->assertEquals('testsourced', $a1->somethingSourced);
        $this->assertEquals('testTargeted', $a1->somethingTargeted);

        $a2 = $mapper->map($c, ClassRuleA::class);
        $this->assertInstanceOf(ClassRuleA::class, $a2);
        $this->assertEquals('TESTSOURCED', $a2->somethingSourced);
        $this->assertEquals('testTargeted', $a2->somethingTargeted);
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
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor());
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

    public function testMapInitializesNativePhp84LazyObject()
    {
        $initialized = false;
        $initializer = static function () use (&$initialized) {
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

    public function testMappingAwareTransformCallable()
    {
        $mapper = new ObjectMapper(
            transformCallableLocator: $this->getServiceLocator([MappingAwareTransformer::class => new MappingAwareTransformer()]),
        );
        $b = $mapper->map(new MappingAwareA());

        $this->assertInstanceOf(MappingAwareB::class, $b);
        $this->assertSame('bar', $b->bar);
    }

    public function testMappingAwareTransformCallableWithTargetSideMapping()
    {
        $mapper = new ObjectMapper(
            transformCallableLocator: $this->getServiceLocator([MappingAwareTransformer::class => new MappingAwareTransformer()]),
        );
        $d = $mapper->map(new MappingAwareC());

        $this->assertInstanceOf(MappingAwareD::class, $d);
        $this->assertSame('source:foo', $d->foo);
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

    public function testMapCollectionWithTargetClass()
    {
        $source = new NestedCollectionOrderSource();
        $source->items = [
            new LineItemSource('Product A', 2, 19.99),
            new LineItemSource('Product B', 1, 49.99),
        ];

        $mapper = new ObjectMapper();
        $target = $mapper->map($source);

        $this->assertInstanceOf(NestedCollectionOrderTarget::class, $target);
        $this->assertCount(2, $target->items);
        $this->assertInstanceOf(LineItemTarget::class, $target->items[0]);
        $this->assertInstanceOf(LineItemTarget::class, $target->items[1]);
        $this->assertSame('Product A', $target->items[0]->productName);
        $this->assertSame(2, $target->items[0]->quantity);
        $this->assertSame(19.99, $target->items[0]->amount);
        $this->assertSame('Product B', $target->items[1]->productName);
        $this->assertSame(1, $target->items[1]->quantity);
        $this->assertSame(49.99, $target->items[1]->amount);
    }

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

    public function testSkipLazyGhostWithClassTransform()
    {
        $service = new LoadedValueService();
        $service->load();

        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $mapper = new ObjectMapper(
            metadataFactory: $metadataFactory,
            transformCallableLocator: $this->getServiceLocator([ServiceLoadedValueTransformer::class => new ServiceLoadedValueTransformer($service, $metadataFactory)])
        );

        $value = new ValueToMap();
        $value->relation = new ValueToMapRelation('test');

        $result = $mapper->map($value);
        $refl = new \ReflectionClass($result->relation);
        $this->assertFalse($refl->isUninitializedLazyObject($result->relation));
        $this->assertSame($result->relation, $service->get());
        $this->assertSame($result->relation->name, 'loaded');
    }

    public function testMapEmbeddedProperties()
    {
        $dto = new UserDto(
            userAddressZipcode: '12345',
            userAddressCity: 'Test City',
            name: 'John Doe'
        );

        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());
        $user = $mapper->map($dto, UserEmbeddedMapping::class);

        $this->assertInstanceOf(UserEmbeddedMapping::class, $user);
        $this->assertSame('John Doe', $user->name);
        $this->assertInstanceOf(Address::class, $user->address);
        $this->assertSame('12345', $user->address->zipcode);
        $this->assertSame('Test City', $user->address->city);
    }

    public function testConditionalMapSource()
    {
        $dto = new ConditionalSourceMapUserDto(
            userAddressZipcode: '12345',
            userAddressCity: 'Test City',
            name: 'John Doe'
        );

        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), PropertyAccess::createPropertyAccessor());
        $mappedUser = $mapper->map($dto, ConditionalSourceMapUser::class);
        $reverseMappedUserDTO = $mapper->map($mappedUser, $dto);

        $this->assertInstanceOf(ConditionalSourceMapUser::class, $mappedUser);
        $this->assertSame('John Doe', $mappedUser->name);

        $this->assertInstanceOf(ConditionalSourceMapAddress::class, $mappedUser->address);
        $this->assertSame('12345', $mappedUser->address->zipcode);
        $this->assertSame('Test City', $mappedUser->address->city);

        $this->assertInstanceOf(ConditionalSourceMapUserDto::class, $reverseMappedUserDTO);
        $this->assertSame('John Doe', $reverseMappedUserDTO->name);
        $this->assertSame('12345', $reverseMappedUserDTO->userAddressZipcode);
        $this->assertSame('Test City', $reverseMappedUserDTO->userAddressCity);
    }

    public function testBugReportLazyLoadingPromotedReadonlyProperty()
    {
        $source = new ReadOnlyPromotedPropertyA(
            b: new ReadOnlyPromotedPropertyB(
                var2: 'bar',
            ),
            var1: 'foo',
        );

        $mapper = new ObjectMapper();
        $out = $mapper->map($source);

        $this->assertInstanceOf(ReadOnlyPromotedPropertyAMapped::class, $out);
        $this->assertInstanceOf(ReadOnlyPromotedPropertyBMapped::class, $out->b);
        $this->assertSame('foo', $out->var1);
        $this->assertSame('bar', $out->b->var2);
    }

    public function testClassMap()
    {
        $classMap = [
            Quote::class => QuoteRequestView::class,
            Cost::class => CostRequestView::class,
        ];

        $quote = new Quote('foo', new Cost(10, 20));

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));

        $quoteRequestView = $mapper->map($quote);

        $this->assertInstanceOf(QuoteRequestView::class, $quoteRequestView);
        $this->assertInstanceOf(CostRequestView::class, $quoteRequestView->cost);
        $this->assertEquals(10, $quoteRequestView->cost->amount);
        $this->assertEquals(20, $quoteRequestView->cost->tax);
    }

    public function testClassMapWithSourceAttribute()
    {
        $classMap = [
            Cost::class => CostRequestWithSourceView::class,
        ];

        $cost = new Cost(10, 20, 'bar');

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));

        $costRequestView = $mapper->map($cost);

        $this->assertInstanceOf(CostRequestWithSourceView::class, $costRequestView);
        $this->assertEquals('bar', $costRequestView->foo);
    }

    public function testClassMapWithSourceAttributeOnPrivateParentProperty()
    {
        $classMap = [
            Cost::class => PrivateMappedChildView::class,
        ];

        $cost = new Cost(10, 20, 'bar');

        $mapper = new ObjectMapper(
            new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap),
            PropertyAccess::createPropertyAccessor(),
        );

        $view = $mapper->map($cost);

        // The "#[Map(source: 'bar')]" attribute sits on a private property
        // inherited from the parent class and must still be discovered.
        $this->assertInstanceOf(PrivateMappedChildView::class, $view);
        $this->assertSame('bar', $view->getFoo());
    }

    public function testClassMapWithSourceAttributeDoesNotBreakAutoMapping()
    {
        $classMap = [
            Cost::class => CostRequestWithSourceAndAutoMappedView::class,
        ];

        $cost = new Cost(10, 20, 'bar');

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));

        $costRequestView = $mapper->map($cost);

        $this->assertInstanceOf(CostRequestWithSourceAndAutoMappedView::class, $costRequestView);
        $this->assertEquals('bar', $costRequestView->foo, 'Explicit mapping should work');
        $this->assertEquals(10, $costRequestView->amount, 'Auto-mapping should also work for properties with the same name');
        $this->assertEquals(20, $costRequestView->tax);
    }

    #[DataProvider('provideAccessor')]
    public function testClassMapIgnoresUnreadableSourcePropertyAbsentFromTarget(?PropertyAccessorInterface $accessor)
    {
        $mapper = new ObjectMapper(
            new ReverseClassObjectMapperMetadataFactory(
                new ReflectionObjectMapperMetadataFactory(),
                [RichDomainUser::class => RichDomainUserView::class],
            ),
            $accessor,
        );

        $view = $mapper->map(new RichDomainUser());

        $this->assertInstanceOf(RichDomainUserView::class, $view);
        $this->assertSame(1, $view->id);
        $this->assertSame('john', $view->username);
    }

    public static function provideAccessor(): iterable
    {
        yield 'with property accessor' => [PropertyAccess::createPropertyAccessor()];
        yield 'without property accessor' => [null];
    }

    #[DataProvider('provideAccessor')]
    public function testNonPublicSourcePropertyWithoutMagicGetIsIgnored(?PropertyAccessorInterface $accessor)
    {
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), $accessor);

        $view = $mapper->map(new Order());

        $this->assertInstanceOf(OrderView::class, $view);
        $this->assertSame('o1', $view->ref);
        $this->assertNull($view->auditTrail);
    }

    #[DataProvider('provideAccessor')]
    public function testNonPublicSourcePropertyExposedByMagicGetIsMapped(?PropertyAccessorInterface $accessor)
    {
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), $accessor);

        $view = $mapper->map(new MagicGetUser());

        $this->assertInstanceOf(MagicGetUserView::class, $view);
        $this->assertSame(1, $view->id);
        $this->assertSame('magic-value', $view->name);
    }

    #[DataProvider('provideAccessor')]
    public function testUninitializedSourcePropertyAbsentFromTargetIsIgnored(?PropertyAccessorInterface $accessor)
    {
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), $accessor);

        $view = $mapper->map(new Draft());

        $this->assertInstanceOf(DraftView::class, $view);
        $this->assertSame('hello', $view->title);
    }

    #[DataProvider('provideAccessor')]
    public function testUninitializedSourcePropertyFallsBackToConstructorDefault(?PropertyAccessorInterface $accessor)
    {
        $mapper = new ObjectMapper(new ReflectionObjectMapperMetadataFactory(), $accessor);

        $view = $mapper->map(new Post());

        $this->assertInstanceOf(PostView::class, $view);
        $this->assertSame('hello', $view->title);
        $this->assertSame('none', $view->summary);
    }

    public function testClassMapWithMultipleTargetsSharingOneSourceAppliesEachTargetsTransform()
    {
        $classMap = [
            SharedSource::class => [
                SharedTargetWithTransform::class,
                SharedTargetWithoutTransform::class,
            ],
        ];

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));

        $withTransform = $mapper->map(new SharedSource(), SharedTargetWithTransform::class);
        $this->assertInstanceOf(SharedTargetWithTransform::class, $withTransform);
        $this->assertSame(42, $withTransform->value);

        $withoutTransform = $mapper->map(new SharedSource(), SharedTargetWithoutTransform::class);
        $this->assertInstanceOf(SharedTargetWithoutTransform::class, $withoutTransform);
        $this->assertSame(21, $withoutTransform->value);
    }

    public function testClassMapKeepsClassLevelTransformDeclaredOnTarget()
    {
        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [Payment::class => PaymentView::class],
        ));

        $view = $mapper->map(new Payment(new Amount(4200)));

        $this->assertInstanceOf(PaymentView::class, $view);
        $this->assertSame(4200, $view->amountCents);

        $view = $mapper->map(new Payment(new Amount(100)), PaymentView::class);

        $this->assertInstanceOf(PaymentView::class, $view);
        $this->assertSame(100, $view->amountCents);
    }

    public function testClassMapKeepsClassLevelTransformDeclaredForAParentSourceClass()
    {
        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [RefundPayment::class => BasePaymentView::class],
        ));

        $view = $mapper->map(new RefundPayment(new Amount(500)));

        $this->assertInstanceOf(BasePaymentView::class, $view);
        $this->assertSame(500, $view->amountCents);
    }

    public function testClassMapKeepsEveryConditionalClassLevelMapDeclaredOnTarget()
    {
        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [Invoice::class => InvoiceView::class],
        ));

        $view = $mapper->map(new Invoice(100, 'EUR'));

        $this->assertInstanceOf(InvoiceView::class, $view);
        $this->assertSame('100 EUR', $view->label);

        $view = $mapper->map(new Invoice(50, 'USD'));

        $this->assertInstanceOf(InvoiceView::class, $view);
        $this->assertSame('50 USD', $view->label);
    }

    public function testClassMapWithMultipleTargetsSharingOneSourceRequiresAnExplicitTarget()
    {
        $classMap = [
            SharedSource::class => [
                SharedTargetWithTransform::class,
                SharedTargetWithoutTransform::class,
            ],
        ];

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Ambiguous mapping for "'.SharedSource::class.'".');

        $mapper->map(new SharedSource());
    }

    public function testClassMapEntryDuplicatingSourceTargetAttributeDoesNotRaiseAmbiguity()
    {
        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [PreMappedSource::class => PreMappedTarget::class],
        ));

        $result = $mapper->map(new PreMappedSource());

        $this->assertInstanceOf(PreMappedTarget::class, $result);
        $this->assertSame(1, $result->value);
    }

    public function testClassMapFilterAcceptsSubclassTarget()
    {
        $classMap = [SharedSource::class => ExtensibleTargetParent::class];

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));
        $result = $mapper->map(new SharedSource(), ExtensibleTargetChild::class);

        $this->assertInstanceOf(ExtensibleTargetChild::class, $result);
        $this->assertSame(42, $result->value);
    }

    public function testNestedAutoMapPicksMatchingTargetFromMultiTargetClassMap()
    {
        $classMap = [
            AutoNestedInnerSource::class => [
                AutoNestedFlatTarget::class,
                AutoNestedOtherTarget::class,
            ],
        ];

        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), $classMap));
        $result = $mapper->map(new AutoNestedOuterSource(), AutoNestedFlatTarget::class);

        $this->assertInstanceOf(AutoNestedFlatTarget::class, $result);
        $this->assertSame('from outer', $result->outer);
        $this->assertSame('from inner', $result->inner);
    }

    public function testMissingSourcePropertiesAreIgnored()
    {
        $mapper = new ObjectMapper();
        $source = new class {
            public string $name = 'test';
        };
        $target = $mapper->map($source, new class {
            public string $name;
            public bool $withDefault = true;
            public string $withoutDefault;
        });

        $this->assertSame('test', $target->name);
        $this->assertTrue($target->withDefault);

        $this->assertFalse(
            (new \ReflectionProperty($target, 'withoutDefault'))->isInitialized($target),
            'Property without default value should remain uninitialized'
        );
    }

    public function testMultipleTargetsWithoutConditionThrowsExceptionWhenNoTargetProvided()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Ambiguous mapping');

        $source = new MultipleTargetPropertyA();
        $mapper = new ObjectMapper();
        $mapper->map($source);
    }

    public function testExplicitTargetIsNotMappedWithTheTransformOfAnotherTarget()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new MultipleTargetsWithTransformSource(), MultipleTargetsWithTransformPlainTarget::class);

        $this->assertInstanceOf(MultipleTargetsWithTransformPlainTarget::class, $target);
        $this->assertSame('test', $target->name);
        $this->assertSame('constructed', $target->label);
    }

    public function testExplicitTargetIsMappedWithTheTransformOfOneOfItsSubclasses()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new SubclassTargetWithTransformSource(), SubclassTargetWithTransformParentTarget::class);

        $this->assertInstanceOf(SubclassTargetWithTransformChildTarget::class, $target);
        $this->assertSame('test', $target->name);
    }

    public function testNestedPropertyWithSeveralMapTargetsIsResolvedByItsDeclaredType()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new MultiTargetOuterSource(), MultiTargetOuterTarget::class);

        $this->assertInstanceOf(InnerTargetA::class, $target->inner);
        $this->assertSame('inner-value', $target->inner->value);
    }

    public function testNestedRenamedPropertyWithSeveralMapTargetsIsResolvedByItsDeclaredType()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new OuterSourceWithRenamedProperty());

        $this->assertInstanceOf(OuterTargetWithRenamedProperty::class, $target);
        $this->assertInstanceOf(InnerTargetA::class, $target->nested);
        $this->assertSame('inner-value', $target->nested->value);
    }

    public function testNestedPropertyWithSeveralMapTargetsThrowsWhenUntyped()
    {
        $mapper = new ObjectMapper();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Ambiguous mapping');

        $mapper->map(new MultiTargetOuterSource(), OuterTargetWithUntypedProperty::class);
    }

    public function testNestedPropertyWithSeveralMapTargetsThrowsWhenTypedWithAnInterfaceBothTargetsImplement()
    {
        $mapper = new ObjectMapper();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Ambiguous mapping');

        $mapper->map(new MultiTargetOuterSource(), OuterTargetWithInterfaceProperty::class);
    }

    public function testNestedPropertyWithSeveralMapTargetsIsLeftAloneWhenNoneMatchesItsDeclaredType()
    {
        $mapper = new ObjectMapper();

        $source = new MultiTargetOuterSource();
        $target = $mapper->map($source, OuterTargetWithInnerSourceProperty::class);

        $this->assertSame($source->inner, $target->inner);
    }

    public function testNestedPropertyWithASingleMapTargetIsLeftAloneWhenItDoesNotMatchItsDeclaredType()
    {
        $mapper = new ObjectMapper();

        $source = new PropertyTypeOuterSource();
        $target = $mapper->map($source, PropertyTypeOuterTarget::class);

        $this->assertSame($source->inner, $target->inner);
    }

    public function testNestedPropertyWithAClassTransformIsMappedWhenOnlyTheTransformMatchesItsDeclaredType()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new KennelSource(), KennelTarget::class);

        $this->assertInstanceOf(Dog::class, $target->pet);
        $this->assertSame('rex', $target->pet->name);
    }

    public function testExplicitMappingTakesPriorityOverImplicitSameNameProperty()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new ExplicitTargetPrioritySource());
        $this->assertSame('from-customerEmail', $target->email);
    }

    public function testExplicitMappingTakesPriorityRegardlessOfDeclarationOrder()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new ExplicitTargetPriorityReversedSource());
        $this->assertSame('from-customerEmail', $target->email);
    }

    public function testSkippedConditionalMappingKeepsImplicitValue()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new ExplicitTargetPriorityConditionalSource());
        $this->assertSame('from-email', $target->email);
    }

    public function testExplicitMappingTakesPriorityOverInheritedImplicitProperty()
    {
        $mapper = new ObjectMapper();

        $target = $mapper->map(new ExplicitTargetPriorityChildSource());

        $this->assertSame('from-customerEmail', $target->email);
    }

    public function testConditionalMappingAppliedToConstructorArguments()
    {
        $mapper = new ObjectMapper();

        $sourceWithNull = new InputSource();
        $targetWithNull = $mapper->map($sourceWithNull);
        $this->assertFalse((new \ReflectionProperty($targetWithNull, 'name'))->isInitialized($targetWithNull));

        $sourceWithValue = new InputSource();
        $sourceWithValue->name = 'test';
        $targetWithValue = $mapper->map($sourceWithValue);
        $this->assertSame('test', $targetWithValue->name);
    }

    public function testNestedBankDataMapping()
    {
        $bankDto = new NestedBankDto();
        $bankDto->bic = 'BIC123';
        $bankDto->code = 'BANK001';
        $bankDto->name = 'Test Bank';

        $bankDataDto = new NestedBankDataDto();
        $bankDataDto->iban = 'IBAN12345';
        $bankDataDto->bank = $bankDto;

        $mapper = new ObjectMapper();
        $bankDataResource = $mapper->map($bankDataDto, NestedBankDataResource::class);

        $this->assertInstanceOf(NestedBankDataResource::class, $bankDataResource);
        $this->assertSame('IBAN12345', $bankDataResource->iban);
        $this->assertSame('BIC123', $bankDataResource->bic);
        $this->assertSame('BANK001', $bankDataResource->bankCode);
        $this->assertSame('Test Bank', $bankDataResource->bankName);
    }

    public function testNestedMergeWithCyclicSource()
    {
        $source = new NestedMergeRecursionSource();
        $source->name = 'root';
        $source->child = $source;

        $mapper = new ObjectMapper();
        $mapped = $mapper->map($source);

        $this->assertInstanceOf(NestedMergeRecursionTarget::class, $mapped);
        $this->assertSame('root', $mapped->name);
    }

    public function testNestedMappingWithClassTransform()
    {
        $target = (new ObjectMapper())->map(new ParentSource());

        $this->assertInstanceOf(ParentTarget::class, $target);
        $this->assertTrue($target->transformed);
        $this->assertInstanceOf(ChildWithClassTransformTarget::class, $target->childWithClassTransformer);
        $this->assertSame('ChildWithClassTransformSource', $target->childWithClassTransformer->name);
        $this->assertTrue($target->childWithClassTransformer->classTransformed);
    }

    public function testNestedMappingWithPropertyTransform()
    {
        $target = (new ObjectMapper())->map(new ParentSource());

        $this->assertInstanceOf(ChildWithoutClassTransformerTarget::class, $target->childWithoutClassTransformer);
        $this->assertSame('child', $target->childWithoutClassTransformer->name);
        $this->assertTrue($target->childWithoutClassTransformer->propertyTransformed);
    }

    public function testNestedMappingWithBothPropertyAndClassTransforms()
    {
        $target = (new ObjectMapper())->map(new ParentSource());

        $this->assertInstanceOf(ChildWithClassTransformTarget::class, $target->childWithBothTransformers);
        $this->assertSame('both', $target->childWithBothTransformers->name);
        $this->assertTrue($target->childWithBothTransformers->classTransformed);
    }

    public function testIsNotNullConditionSkipsNullProperties()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSource(name: 'Alice');
        $target = $mapper->map($source);
        $this->assertInstanceOf(IsNotNullTarget::class, $target);
        $this->assertSame('Alice', $target->name);
        $this->assertNull($target->age);
    }

    public function testIsNotNullConditionPreservesExistingTargetValues()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSource(name: 'Bob');
        $target = new IsNotNullTarget();
        $target->age = 30;

        $mapped = $mapper->map($source, $target);
        $this->assertSame('Bob', $mapped->name);
        $this->assertSame(30, $mapped->age);
    }

    public function testIsNotNullConditionMapsAllNonNullValues()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSource(name: 'Charlie', age: 25);
        $target = $mapper->map($source);
        $this->assertSame('Charlie', $target->name);
        $this->assertSame(25, $target->age);
    }

    public function testIsNotNullConditionWithSourceMappingSkipsNullProperties()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSourceMapping(firstName: 'Alice');
        $target = $mapper->map($source, new IsNotNullTargetMapping());
        $this->assertSame('Alice', $target->name);
        $this->assertNull($target->points);
    }

    public function testIsNotNullConditionWithSourceMappingPreservesExistingTargetValues()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSourceMapping(firstName: 'Bob');
        $target = new IsNotNullTargetMapping();
        $target->points = 100;

        $mapped = $mapper->map($source, $target);
        $this->assertSame('Bob', $mapped->name);
        $this->assertSame(100, $mapped->points);
    }

    public function testIsNotNullConditionWithSourceMappingMapsAllNonNullValues()
    {
        $mapper = new ObjectMapper();

        $source = new IsNotNullSourceMapping(firstName: 'Charlie', score: 42);
        $target = $mapper->map($source, new IsNotNullTargetMapping());
        $this->assertSame('Charlie', $target->name);
        $this->assertSame(42, $target->points);
    }

    public function testClassRuleListRejectsEmptyArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A ClassRuleList needs at least one rule.');

        new ClassRuleList([]);
    }

    public function testClassRuleWithBothSourcesAndTargets()
    {
        $rule = new ClassRule(sources: [ClassRuleB::class], targets: [ClassRuleA::class]);

        $this->assertTrue($rule(null, new ClassRuleB(), new ClassRuleA()));
        $this->assertFalse($rule(null, new ClassRuleC(), new ClassRuleA()), 'wrong source rejects');
        $this->assertFalse($rule(null, new ClassRuleB(), new ClassRuleC()), 'wrong target rejects');
    }

    public function testClassRuleConditionInvokedOnce()
    {
        $calls = 0;
        $rule = new class($calls) implements \Symfony\Component\ObjectMapper\Condition\ClassRuleConditionCallableInterface {
            public function __construct(private int &$calls)
            {
            }

            public function __invoke(mixed $value, object $source, ?object $target): bool
            {
                ++$this->calls;

                return true;
            }
        };

        $source = new class {
            public string $foo = 'bar';
        };
        $target = new class {
            public string $foo = '';
        };

        $factory = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $factory->method('create')->willReturnCallback(static function (object $o, ?string $property = null) use ($rule, $source) {
            if ($o === $source && 'foo' === $property) {
                return [new Mapping(target: 'foo', if: $rule::class)];
            }

            return [];
        });

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(static fn ($id) => $id === $rule::class);
        $locator->method('get')->willReturnCallback(static fn ($id) => $id === $rule::class ? $rule : null);

        (new ObjectMapper($factory, null, null, $locator))->map($source, $target);

        $this->assertSame(1, $calls);
    }

    public function testReverseClassObjectMapperMetadataFactoryWithMissingTargetClass()
    {
        $source = new Cost(10, 20, 'bar');

        $factory = new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [Cost::class => 'Nonexistent\\Class\\ForReverseFactoryTest'],
        );

        $this->expectException(\ReflectionException::class);
        $factory->create($source, 'amount');
    }

    public function testSelfReferencingPropertyIsNotMergedIntoTarget()
    {
        $root = new Category(1, 'root');
        $child = new Category(2, 'child');
        $child->parent = $root;

        $mapper = new ObjectMapper();
        $dto = $mapper->map($child);

        $this->assertInstanceOf(CategoryDto::class, $dto);
        $this->assertSame(2, $dto->id);
        $this->assertSame('child', $dto->name);
    }

    public function testMapsPrivatePropertyFromParentClassWithPropertyAccessor()
    {
        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());

        $source = new ChildEntity(id: 42, name: 'Test');
        $target = $mapper->map($source);

        $this->assertInstanceOf(ChildEntityDto::class, $target);
        $this->assertSame(42, $target->id);
        $this->assertSame('Test', $target->name);
    }

    public function testPrivatePropertyFromParentClassIsIgnoredWithoutPropertyAccessor()
    {
        $mapper = new ObjectMapper();

        $source = new ChildEntity(id: 42, name: 'Test');
        $target = $mapper->map($source);

        $this->assertInstanceOf(ChildEntityDto::class, $target);
        // without a property accessor, getId() is not discoverable and a non-public property is only readable through magic __get()
        $this->assertNull($target->id);
        $this->assertSame('Test', $target->name);
    }

    public function testRedeclaredPrivateParentPropertyResolvesToChildVersion()
    {
        $mapper = new ObjectMapper();

        $source = new RedeclaredChildEntity(tag: 'overridden');
        $target = $mapper->map($source);

        $this->assertInstanceOf(RedeclaredChildEntityDto::class, $target);
        $this->assertSame('overridden', $target->tag);
    }

    public function testMapsPrivateParentPropertyToConstructorlessTarget()
    {
        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());

        $source = new ChildEntity(id: 42, name: 'Test');
        $target = $mapper->map($source, ConstructorlessChildEntityDto::class);

        // the target has no constructor, so the value cannot come through the constructor-arguments path:
        // the private parent $id is mapped only because it is now discovered as a source property
        $this->assertSame(42, $target->id);
        $this->assertSame('Test', $target->name);
    }

    public function testMappedPrivateParentPropertyWithoutAccessorIsSkippedWithoutWarning()
    {
        $mapper = new ObjectMapper();

        $source = new MappedChildEntity(name: 'Test');
        $target = $mapper->map($source);

        // the parent's private property carries a #[Map] but is unreadable without an accessor or magic __get(),
        // so it is skipped rather than read (which would emit an "Undefined property" warning)
        $this->assertInstanceOf(MappedChildEntityDto::class, $target);
        $this->assertSame('Test', $target->name);
        $this->assertNull($target->secret);
    }

    public function testTransformReceivesMappedSourceValueWithNonThrowingPropertyAccessor()
    {
        $mapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor());

        $target = $mapper->map(new TransformSourcePropertySource(), TransformSourcePropertyTarget::class);

        // a non-throwing property accessor must not make the unrelated target property name look readable on the
        // source, otherwise the #[Map(source: ...)] property is read as null and the transform receives null
        $this->assertInstanceOf(TransformSourcePropertyTarget::class, $target);
        $this->assertSame('ABC', $target->targetProperty);
    }

    public function testNestedTargetIsConstructed()
    {
        $mapper = new ObjectMapper();
        $mapped = $mapper->map(new Outer(new Inner('bar')));

        $this->assertInstanceOf(InnerMapped::class, $mapped->inner);
        $this->assertSame('bar', $mapped->inner->name);
        // computed by the constructor, so it can only be set if the constructor ran
        $this->assertSame('slug-of-bar', $mapped->inner->slug);
    }

    public function testSameNameTargetPropertyMappingIsHonoredWhenSourceCarriesMetadata()
    {
        // Lead carries a class-level #[Map] to an unrelated view, so ObjectMapper reads metadata from
        // the source side. The #[Map(transform)] declared on LeadDto::$type must still be applied to the
        // same-name copy; otherwise the raw Type reaches the typed constructor argument and throws.
        $dto = (new ObjectMapper())->map(new SourceCarriesMetadataLead(), SourceCarriesMetadataLeadDto::class);

        $this->assertInstanceOf(SourceCarriesMetadataTypeDto::class, $dto->type);
        $this->assertSame(7, $dto->type->id);
        $this->assertSame('moving', $dto->type->name);
    }

    public function testSameNameCopyIgnoresAMappingSynthesizedForAnotherTarget()
    {
        // Target is itself a source in the class map, so the reverse factory synthesizes a mapping
        // for its "label" property carrying OtherView as its target class. That transform belongs to
        // the Target to OtherView direction and must not be applied when mapping Source to Target.
        $mapper = new ObjectMapper(new ReverseClassObjectMapperMetadataFactory(
            new ReflectionObjectMapperMetadataFactory(),
            [TargetInClassMapTarget::class => TargetInClassMapOtherView::class],
        ));

        $this->assertSame('lower', $mapper->map(new TargetInClassMapSource(), TargetInClassMapTarget::class)->label);
    }

    public function testRecursionCacheIsNotReusedForADifferentTarget()
    {
        // mapping to ItemSummaryTarget caches it for $item, while the back-reference
        // ChildTarget::$item resolves to the other target ItemSource declares
        $item = new RecursionCacheItemSource();
        $child = new RecursionCacheChildSource();
        $child->label = 'child';
        $child->item = $item;
        $item->children = [$child];

        $mapped = (new ObjectMapper())->map($item, RecursionCacheItemSummaryTarget::class);

        $this->assertInstanceOf(RecursionCacheItemTarget::class, $mapped->children[0]->item);
    }

    public function testSelfReferenceIsNotReusedForADifferentTarget()
    {
        $source = new RecursionCacheSelfSource();
        $source->self = $source;

        $mapped = (new ObjectMapper())->map($source, RecursionCacheSelfTarget::class);

        $this->assertInstanceOf(RecursionCacheSelfSummaryTarget::class, $mapped->self);
        $this->assertSame(1, $mapped->self->id);
    }

    public function testDirectionlessTargetTransformIsNotAppliedToTheSameNameCopy()
    {
        $product = (new ObjectMapper())->map(new DirectionlessTransformProductInput(), DirectionlessTransformProduct::class);

        $this->assertSame('sf-1', $product->reference);
    }

    public function testDirectionlessTransformIsAppliedWhenItsOwnClassIsTheSource()
    {
        $input = (new ObjectMapper())->map(new DirectionlessTransformProduct(), DirectionlessTransformProductInput::class);

        $this->assertSame('SF-1', $input->reference);
    }
}
