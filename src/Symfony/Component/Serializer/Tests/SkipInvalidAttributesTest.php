<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\StringBackedEnumDummy;

class SkipInvalidAttributesTest extends TestCase
{
    private const SKIP = [AbstractNormalizer::SKIP_INVALID_ATTRIBUTES => true];

    public function testConstructorArgumentFallsBackToItsDefault()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope', 'query' => 'foo'], SkipInvalidDummy::class, null, self::SKIP);

        $this->assertSame(1, $dummy->page);
        $this->assertSame('foo', $dummy->query);
    }

    public function testConstructorArgumentInsideACollectionFallsBackToItsDefault()
    {
        $basket = $this->createSerializer()->denormalize(['lines' => [['quantity' => 'nope'], ['quantity' => 3]]], SkipInvalidBasket::class, null, self::SKIP);

        $this->assertCount(2, $basket->lines);
        $this->assertSame(0, $basket->lines[0]->quantity);
        $this->assertSame(3, $basket->lines[1]->quantity);
    }

    public function testRenamedConstructorArgumentFallsBackToItsDefault()
    {
        $dummy = $this->createSerializer()->denormalize(['p' => 'nope'], SkipInvalidRenamedDummy::class, null, self::SKIP);

        $this->assertSame(7, $dummy->page);
    }

    public function testConstructorArgumentFallsBackToTheDefaultConstructorArgumentsOption()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidDummy::class, null, self::SKIP + [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [SkipInvalidDummy::class => ['page' => 42]],
        ]);

        $this->assertSame(42, $dummy->page);
    }

    public function testNullableConstructorArgumentWithoutDefaultFallsBackToNull()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidNullableDummy::class, null, self::SKIP);

        $this->assertNull($dummy->page);
    }

    public function testRequiredConstructorArgumentIsReportedAsMissing()
    {
        $this->expectException(MissingConstructorArgumentsException::class);
        $this->expectExceptionMessage('Cannot create an instance of "'.SkipInvalidRequiredDummy::class.'" from serialized data because its constructor requires the following parameters to be present : "$page".');

        $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidRequiredDummy::class, null, self::SKIP);
    }

    public function testAbsentRequiredConstructorArgumentIsUnaffected()
    {
        $this->expectException(MissingConstructorArgumentsException::class);
        $this->expectExceptionMessage('Cannot create an instance of "'.SkipInvalidRequiredDummy::class.'" from serialized data because its constructor requires the following parameters to be present : "$page".');

        $this->createSerializer()->denormalize([], SkipInvalidRequiredDummy::class, null, self::SKIP);
    }

    public function testInvalidVariadicArgumentIsDropped()
    {
        $dummy = $this->createSerializer()->denormalize(['methods' => ['GET', 'nope', 'OPTIONS']], SkipInvalidVariadicDummy::class, null, self::SKIP);

        $this->assertSame([StringBackedEnumDummy::GET, StringBackedEnumDummy::OPTIONS], $dummy->methods);
    }

    public function testPropertyKeepsItsDefault()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope', 'query' => 'foo'], SkipInvalidPropertyDummy::class, null, self::SKIP);

        $this->assertSame(1, $dummy->page);
        $this->assertSame('foo', $dummy->query);
    }

    public function testPropertyWithoutDefaultStaysUninitialized()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidTypedPropertyDummy::class, null, self::SKIP);

        $this->assertFalse((new \ReflectionProperty($dummy, 'page'))->isInitialized($dummy));
    }

    public function testValidValuesAreUnaffected()
    {
        $serializer = $this->createSerializer();

        $dummy = $serializer->denormalize(['page' => 5, 'query' => 'foo'], SkipInvalidDummy::class, null, self::SKIP);
        $this->assertSame(5, $dummy->page);
        $this->assertSame('foo', $dummy->query);

        $dummy = $serializer->denormalize(['page' => 5, 'query' => 'foo'], SkipInvalidPropertyDummy::class, null, self::SKIP);
        $this->assertSame(5, $dummy->page);
        $this->assertSame('foo', $dummy->query);
    }

    public function testNoPartialDenormalizationExceptionIsThrown()
    {
        $serializer = $this->createSerializer();

        $this->assertInstanceOf(SkipInvalidDummy::class, $serializer->denormalize(['page' => 'nope'], SkipInvalidDummy::class, null, self::SKIP));
        $this->assertInstanceOf(SkipInvalidPropertyDummy::class, $serializer->denormalize(['page' => 'nope'], SkipInvalidPropertyDummy::class, null, self::SKIP));
    }

    public function testSkippedValuesAreNotCollectedAsErrors()
    {
        $dummy = $this->createSerializer()->denormalize(['page' => 'nope', 'query' => 'foo'], SkipInvalidDummy::class, null, self::SKIP + [
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
        ]);

        $this->assertSame(1, $dummy->page);
        $this->assertSame('foo', $dummy->query);
    }

    public function testRemainingErrorsAreStillCollected()
    {
        try {
            $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidRequiredDummy::class, null, self::SKIP + [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);

            $this->fail(\sprintf('Expected a "%s" to be thrown.', PartialDenormalizationException::class));
        } catch (PartialDenormalizationException $e) {
            $errors = $e->getNotNormalizableValueErrors();
        }

        $this->assertCount(1, $errors);
        $this->assertSame('Failed to create object because the class misses the "page" property.', $errors[0]->getMessage());
    }

    public function testErrorsAreReportedWithoutTheOption()
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->createSerializer()->denormalize(['page' => 'nope'], SkipInvalidDummy::class);
    }

    public function testOptionCanBeSetInTheDefaultContext()
    {
        $serializer = $this->createSerializer(self::SKIP);

        $this->assertSame(1, $serializer->denormalize(['page' => 'nope'], SkipInvalidDummy::class)->page);
        $this->assertSame(1, $serializer->denormalize(['page' => 'nope'], SkipInvalidPropertyDummy::class)->page);
    }

    private function createSerializer(array $defaultContext = []): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        return new Serializer([
            new ArrayDenormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor, null, null, $defaultContext),
        ]);
    }
}

class SkipInvalidDummy
{
    public function __construct(
        public int $page = 1,
        public string $query = '',
    ) {
    }
}

class SkipInvalidLine
{
    public function __construct(
        public int $quantity = 0,
    ) {
    }
}

class SkipInvalidBasket
{
    /**
     * @param SkipInvalidLine[] $lines
     */
    public function __construct(
        public array $lines = [],
    ) {
    }
}

class SkipInvalidRenamedDummy
{
    public function __construct(
        #[SerializedName('p')]
        public int $page = 7,
    ) {
    }
}

class SkipInvalidNullableDummy
{
    public function __construct(
        public ?int $page,
    ) {
    }
}

class SkipInvalidRequiredDummy
{
    public function __construct(
        public int $page,
    ) {
    }
}

class SkipInvalidVariadicDummy
{
    public array $methods;

    public function __construct(StringBackedEnumDummy ...$methods)
    {
        $this->methods = $methods;
    }
}

class SkipInvalidPropertyDummy
{
    public int $page = 1;
    public string $query = '';
}

class SkipInvalidTypedPropertyDummy
{
    public int $page;
}
