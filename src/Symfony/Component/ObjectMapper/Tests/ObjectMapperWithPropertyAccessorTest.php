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
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\AdderTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\ArrayObjectSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\ConstructorTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\DateSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\DateTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\GetterSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\HookedSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\InfoExtractorSpy;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\MagicGetSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\MagicIssetSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\MagicSetTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\MismatchedTypeSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\MismatchedTypeTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\PlainSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\PlainTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\ReadonlyTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\SetterTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess\TransformedSource;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;

class ObjectMapperWithPropertyAccessorTest extends TestCase
{
    public function testPublicPropertiesAreReadAndWrittenWithoutThePropertyAccessor()
    {
        $spy = new InfoExtractorSpy(new ReflectionExtractor());
        $mapper = self::createMapper(new PropertyAccessor(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, null, $spy, $spy));

        $target = $mapper->map(new PlainSource(), PlainTarget::class);

        $this->assertSame('jane', $target->name);
        $this->assertNull($target->nickname);
        $this->assertSame(['a', 'b'], $target->tags);
        $this->assertSame(7, $target->count);
        $this->assertSame([], $spy->lookups);
    }

    #[DataProvider('providePropertiesLeftToThePropertyAccessor')]
    public function testPropertiesNotAccessedAsPlainPublicOnesAreLeftToThePropertyAccessor(object $source, string $targetClass, array $expected)
    {
        $target = self::createMapper()->map($source, $targetClass);

        foreach ($expected as $property => $value) {
            $this->assertEquals($value, $target->$property);
        }
    }

    public static function providePropertiesLeftToThePropertyAccessor(): iterable
    {
        yield 'getter' => [new GetterSource(), PlainTarget::class, ['name' => 'getter']];
        yield '__get() of an unset property' => [new MagicGetSource(), PlainTarget::class, ['name' => 'magic']];
        yield '__isset() of an unset property' => [new MagicIssetSource(), ConstructorTarget::class, ['name' => 'default']];
        yield 'hooked property' => [new HookedSource(), ConstructorTarget::class, ['name' => null]];
        yield 'property of an ArrayObject' => [new ArrayObjectSource(), ConstructorTarget::class, ['name' => null]];
        yield 'setter' => [new PlainSource(), SetterTarget::class, ['name' => 'JANE']];
        yield 'adder and remover' => [new PlainSource(), AdderTarget::class, ['tags' => ['A', 'B']]];
        yield 'readonly and private(set)' => [new PlainSource(), ReadonlyTarget::class, ['name' => 'constructor', 'nickname' => 'none']];
        yield 'child class returned by a transform' => [new TransformedSource(), PlainTarget::class, ['name' => 'JANE']];
        yield 'DateTime converted by the accessor' => [new DateSource(), DateTarget::class, ['at' => new \DateTimeImmutable('2024-01-02 03:04:05')]];
    }

    public function testTypeMismatchThrowsTheExceptionOfThePropertyAccessor()
    {
        $this->expectException(InvalidTypeException::class);

        self::createMapper()->map(new PlainSource(), MismatchedTypeTarget::class);
    }

    public function testTypeMismatchCallsMagicSetOnce()
    {
        $target = new MagicSetTarget();

        try {
            self::createMapper()->map(new MismatchedTypeSource(), $target);
            $this->fail('An exception should have been thrown.');
        } catch (InvalidTypeException) {
        }

        $this->assertSame(['count'], $target->calls);
    }

    public function testDecoratedPropertyAccessorIsCalledForEveryProperty()
    {
        $propertyAccessor = new class(new PropertyAccessor()) implements PropertyAccessorInterface {
            public function __construct(
                private readonly PropertyAccessorInterface $propertyAccessor,
            ) {
            }

            public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
            {
                $this->propertyAccessor->setValue($objectOrArray, $propertyPath, \is_string($value) ? strtoupper($value) : $value);
            }

            public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
            {
                return $this->propertyAccessor->getValue($objectOrArray, $propertyPath);
            }

            public function isWritable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
            {
                return $this->propertyAccessor->isWritable($objectOrArray, $propertyPath);
            }

            public function isReadable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
            {
                return $this->propertyAccessor->isReadable($objectOrArray, $propertyPath);
            }
        };

        $this->assertSame('JANE', self::createMapper($propertyAccessor)->map(new PlainSource(), PlainTarget::class)->name);
    }

    private static function createMapper(?PropertyAccessorInterface $propertyAccessor = null): ObjectMapper
    {
        $extractor = new ReflectionExtractor();
        $propertyAccessor ??= new PropertyAccessor(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, null, $extractor, $extractor);
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $classMetadataFactory = new ReflectionClassMetadataFactory($metadataFactory);

        return new ObjectMapper($metadataFactory, $propertyAccessor, null, null, null, $classMetadataFactory, null, new ReflectionPropertyMetadataFactory($metadataFactory, $classMetadataFactory, $propertyAccessor, $extractor, $extractor));
    }
}
