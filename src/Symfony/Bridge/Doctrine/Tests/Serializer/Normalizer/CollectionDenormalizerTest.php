<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Serializer\Normalizer\CollectionDenormalizer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\TypeInfo\Type;

class CollectionDenormalizerTest extends TestCase
{
    public function testSupportsDoctrineCollections()
    {
        $normalizer = new CollectionDenormalizer();
        $context = ['value_type' => Type::string()];

        $this->assertTrue($normalizer->supportsDenormalization([], Collection::class, null, $context));
        $this->assertTrue($normalizer->supportsDenormalization([], ArrayCollection::class, null, $context));
        $this->assertFalse($normalizer->supportsDenormalization([], \stdClass::class, null, $context));
        $this->assertFalse($normalizer->supportsDenormalization([], DummyCollectionInterface::class, null, $context));
    }

    public function testDoesNotSupportCollectionsWithoutElementType()
    {
        $normalizer = new CollectionDenormalizer();

        $this->assertFalse($normalizer->supportsDenormalization([], Collection::class));
        $this->assertFalse($normalizer->supportsDenormalization([], Collection::class, null, ['value_type' => Type::mixed()]));
    }

    public function testDoesNotSupportCollectionsThatCannotBeInstantiated()
    {
        $normalizer = new CollectionDenormalizer();

        $context = ['value_type' => Type::string()];

        $this->assertFalse($normalizer->supportsDenormalization([], AbstractCollection::class, null, $context));
        $this->assertFalse($normalizer->supportsDenormalization([], CollectionWithRequiredConstructorArgument::class, null, $context));
    }

    public function testConstructorRejectsClassesThatAreNotDoctrineCollections()
    {
        $this->expectException(InvalidArgumentException::class);

        new CollectionDenormalizer(\ArrayObject::class);
    }

    public function testDenormalizeRejectsNonArrayData()
    {
        $normalizer = new CollectionDenormalizer();

        $this->expectException(NotNormalizableValueException::class);

        $normalizer->denormalize('not an array', Collection::class);
    }

    public function testDenormalizeUsesArrayCollectionForTheCollectionInterface()
    {
        $serializer = $this->createSerializer();

        $collection = $serializer->denormalize([['value' => 'foo'], ['value' => 'bar']], Collection::class, 'json', ['value_type' => Type::object(DummyItem::class)]);

        $this->assertInstanceOf(ArrayCollection::class, $collection);
        $this->assertContainsOnlyInstancesOf(DummyItem::class, $collection);
        $this->assertSame(['foo', 'bar'], $collection->map(static fn (DummyItem $item) => $item->value)->toArray());
    }

    public function testDenormalizeUsesTheRequestedConcreteCollectionClass()
    {
        $serializer = $this->createSerializer();

        $collection = $serializer->denormalize([['value' => 'foo']], DummyCollection::class, 'json', ['value_type' => Type::object(DummyItem::class)]);

        $this->assertInstanceOf(DummyCollection::class, $collection);
        $this->assertSame('foo', $collection->first()->value);
    }

    public function testDenormalizeUsesTheConfiguredDefaultCollectionClass()
    {
        $serializer = new Serializer([new CollectionDenormalizer(DummyCollection::class), new ArrayDenormalizer(), new ObjectNormalizer()]);

        $collection = $serializer->denormalize(['a' => 'b'], Collection::class, null, ['value_type' => Type::string()]);

        $this->assertInstanceOf(DummyCollection::class, $collection);
        $this->assertSame('b', $collection->get('a'));
    }

    public function testDenormalizeKeepsScalarElements()
    {
        $serializer = $this->createSerializer();

        $collection = $serializer->denormalize(['a', 'b'], Collection::class, 'json', ['value_type' => Type::string()]);

        $this->assertInstanceOf(ArrayCollection::class, $collection);
        $this->assertSame(['a', 'b'], $collection->toArray());
    }

    public function testDenormalizeRejectsElementsOfAnotherScalarType()
    {
        $serializer = $this->createSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type of the element "1" must be "string" ("int" given).');

        $serializer->denormalize(['a', 1], Collection::class, 'json', ['value_type' => Type::string()]);
    }

    public function testDenormalizeKeepsTheElementsTheSerializerAlreadyDenormalized()
    {
        $item = new DummyItem('foo');

        $collection = $this->createSerializer()->denormalize([$item], Collection::class, 'json', ['value_type' => Type::object(DummyItem::class)]);

        $this->assertSame([$item], $collection->toArray());
    }

    public function testDenormalizeKeepsNullElementsWhenTheElementTypeIsNullable()
    {
        $serializer = $this->createSerializer();

        $collection = $serializer->denormalize([['value' => 'foo'], null], Collection::class, 'json', ['value_type' => Type::nullable(Type::object(DummyItem::class))]);

        $this->assertInstanceOf(ArrayCollection::class, $collection);
        $this->assertInstanceOf(DummyItem::class, $collection->get(0));
        $this->assertNull($collection->get(1));
    }

    public function testDenormalizeDoesNotWriteIntoTheCollectionToPopulate()
    {
        $existing = new ArrayCollection([0 => $before = new DummyItem('before')]);
        $serializer = $this->createSerializer();

        $collection = $serializer->denormalize([0 => ['value' => 'after']], Collection::class, 'json', ['value_type' => Type::object(DummyItem::class), 'object_to_populate' => $existing]);

        $this->assertNotSame($existing, $collection);
        $this->assertSame([$before], $existing->toArray());
        $this->assertSame('before', $before->value);
        $this->assertSame('after', $collection->get(0)->value);
    }

    public function testDenormalizeRejectsAnInvalidKeyType()
    {
        $serializer = $this->createSerializer();

        $this->expectException(NotNormalizableValueException::class);

        $serializer->denormalize(['foo' => 'bar'], Collection::class, 'json', ['key_type' => Type::int(), 'value_type' => Type::string()]);
    }

    public function testDenormalizeEntityWithACollectionProperty()
    {
        $serializer = $this->createSerializer();

        $owner = $serializer->denormalize(['items' => [['value' => 'foo'], ['value' => 'bar']]], DummyOwner::class);

        $this->assertInstanceOf(ArrayCollection::class, $owner->items);
        $this->assertContainsOnlyInstancesOf(DummyItem::class, $owner->items);
        $this->assertSame(['foo', 'bar'], $owner->items->map(static fn (DummyItem $item) => $item->value)->toArray());
    }

    public function testDeepPopulateReplacesTheElementsThroughTheAdderAndTheRemover()
    {
        $owner = new DummyOwnerWithAdder();
        $owner->addItem($foo = new DummyItem('foo'));
        $owner->addItem($bar = new DummyItem('bar'));
        $owner->calls = [];
        $items = $owner->getItems();

        $this->createSerializer()->denormalize(['items' => [['value' => 'baz']]], DummyOwnerWithAdder::class, null, [AbstractNormalizer::OBJECT_TO_POPULATE => $owner, AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true]);

        $this->assertSame('foo', $foo->value);
        $this->assertSame('bar', $bar->value);
        $this->assertSame(['remove foo', 'remove bar', 'add baz'], $owner->calls);
        $this->assertSame($items, $owner->getItems());
        $this->assertSame(['baz'], $items->map(static fn (DummyItem $item) => $item->value)->getValues());
    }

    public function testDenormalizeCollectsInvalidScalarElements()
    {
        try {
            $this->createSerializer()->denormalize(['numbers' => [1, 'x', 3]], DummyOwnerOfNumbers::class, null, [DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true]);
            $this->fail('A PartialDenormalizationException was expected.');
        } catch (PartialDenormalizationException $e) {
            $this->assertSame(['numbers[1]'], array_map(static fn (NotNormalizableValueException $error) => $error->getPath(), $e->getNotNormalizableValueErrors()));
            $this->assertSame([0 => 1, 2 => 3], $e->getData()->numbers->toArray());
        }
    }

    public function testDenormalizeNestedCollections()
    {
        $owner = $this->createSerializer()->denormalize(['groups' => [[['value' => 'a']], [['value' => 'b'], ['value' => 'c']]]], DummyOwnerOfGroups::class);

        $this->assertInstanceOf(ArrayCollection::class, $owner->groups);
        $this->assertContainsOnlyInstancesOf(ArrayCollection::class, $owner->groups);
        $this->assertSame([['a'], ['b', 'c']], $owner->groups->map(static fn (Collection $group) => $group->map(static fn (DummyItem $item) => $item->value)->toArray())->toArray());
    }

    public function testDenormalizeUnionElements()
    {
        $owner = $this->createSerializer()->denormalize(['shapes' => [['radius' => 1], ['side' => 2]]], DummyOwnerOfShapes::class);

        $this->assertInstanceOf(DummyCircle::class, $owner->shapes[0]);
        $this->assertSame(1, $owner->shapes[0]->radius);
        $this->assertInstanceOf(DummySquare::class, $owner->shapes[1]);
        $this->assertSame(2, $owner->shapes[1]->side);
    }

    #[DataProvider('provideTypeExtractors')]
    public function testDenormalizeEntityWithALegacyCollectionUnionDocblock(PropertyTypeExtractorInterface $typeExtractor)
    {
        $serializer = new Serializer([new CollectionDenormalizer(), new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, $typeExtractor)]);

        $owner = $serializer->denormalize(['items' => [['value' => 'foo'], ['value' => 'bar']]], LegacyDummyOwner::class);

        $this->assertContainsOnlyInstancesOf(DummyItem::class, $owner->getItems());
        $this->assertSame(['foo', 'bar'], $owner->getItems()->map(static fn (DummyItem $item) => $item->value)->toArray());
    }

    public static function provideTypeExtractors(): iterable
    {
        yield 'phpstan' => [new PropertyInfoExtractor([], [new PhpStanExtractor(), new ReflectionExtractor()])];
        yield 'phpdoc' => [new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()])];
    }

    private function createSerializer(): Serializer
    {
        $propertyInfo = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        return new Serializer([
            new CollectionDenormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $propertyInfo),
        ]);
    }
}

class DummyItem
{
    public function __construct(
        public ?string $value = null,
    ) {
    }
}

class DummyCollection extends ArrayCollection
{
}

class DummyOwner
{
    /** @var Collection<int, DummyItem> */
    public Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }
}

interface DummyCollectionInterface extends Collection
{
}

class DummyOwnerWithAdder
{
    public array $calls = [];

    /** @var Collection<int, DummyItem> */
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    /** @return Collection<int, DummyItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DummyItem $item): void
    {
        $this->calls[] = 'add '.$item->value;
        $this->items->add($item);
    }

    public function removeItem(DummyItem $item): void
    {
        $this->calls[] = 'remove '.$item->value;
        $this->items->removeElement($item);
    }
}

class DummyOwnerOfNumbers
{
    /** @var Collection<int, int> */
    public Collection $numbers;
}

class DummyOwnerOfGroups
{
    /** @var Collection<int, Collection<int, DummyItem>> */
    public Collection $groups;
}

class DummyCircle
{
    public function __construct(
        public int $radius,
    ) {
    }
}

class DummySquare
{
    public function __construct(
        public int $side,
    ) {
    }
}

class DummyOwnerOfShapes
{
    /** @var Collection<int, DummyCircle|DummySquare> */
    public Collection $shapes;
}

class LegacyDummyOwner
{
    private $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    /**
     * @return Collection|DummyItem[]
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DummyItem $item): void
    {
        $this->items->add($item);
    }

    public function removeItem(DummyItem $item): void
    {
        $this->items->removeElement($item);
    }
}

abstract class AbstractCollection extends ArrayCollection
{
}

class CollectionWithRequiredConstructorArgument extends ArrayCollection
{
    public function __construct(string $required)
    {
        parent::__construct();
    }
}
