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
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Metadata\EnumMappingMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumWithExplicitMapSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumWithExplicitMapTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ImplicitEnum;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ImplicitScalar;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\IntPriority;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\PureColor;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StringStatus;

final class ObjectMapperWithEnumTest extends TestCase
{
    public function testMapBackedEnumToScalar()
    {
        $withSourceMetadata = new EnumSource();
        $withSourceMetadata->status = StringStatus::Active;
        $withSourceMetadata->priority = IntPriority::High;
        $withTargetMetadata = new class {
            public StringStatus $status;
            public IntPriority $priority;
        };
        $withTargetMetadata->status = StringStatus::Active;
        $withTargetMetadata->priority = IntPriority::High;

        $viaSource = $this->createMapper()->map($withSourceMetadata);
        $viaTarget = $this->createMapper()->map($withTargetMetadata, ScalarTarget::class);

        $this->assertSame('active', $viaSource->status);
        $this->assertSame(2, $viaSource->priority);
        $this->assertSame('active', $viaTarget->status);
        $this->assertSame(2, $viaTarget->priority);
    }

    public function testMapScalarToBackedEnum()
    {
        $withSourceMetadata = new ScalarSource();
        $withSourceMetadata->status = 'active';
        $withSourceMetadata->priority = 1;
        $withTargetMetadata = new class {
            public string $status;
            public int $priority;
        };
        $withTargetMetadata->status = 'active';
        $withTargetMetadata->priority = 1;

        $viaSource = $this->createMapper()->map($withSourceMetadata);
        $viaTarget = $this->createMapper()->map($withTargetMetadata, EnumTarget::class);

        $this->assertSame(StringStatus::Active, $viaSource->status);
        $this->assertSame(IntPriority::Low, $viaSource->priority);
        $this->assertSame(StringStatus::Active, $viaTarget->status);
        $this->assertSame(IntPriority::Low, $viaTarget->priority);
    }

    public function testMapEnumToScalarWithExplicitMapAttribute()
    {
        $source = new EnumWithExplicitMapSource();
        $source->status = StringStatus::Inactive;

        $target = $this->createMapper()->map($source);

        $this->assertInstanceOf(EnumWithExplicitMapTarget::class, $target);
        $this->assertSame('inactive', $target->targetStatus);
    }

    public function testMapEnumToScalarWithoutPropertyMapAttribute()
    {
        $source = new ImplicitEnum();
        $source->status = StringStatus::Active;
        $source->priority = IntPriority::High;

        $target = $this->createMapper()->map($source, ImplicitScalar::class);

        $this->assertInstanceOf(ImplicitScalar::class, $target);
        $this->assertSame('active', $target->status);
        $this->assertSame(2, $target->priority);
    }

    public function testMapScalarToEnumWithoutPropertyMapAttribute()
    {
        $source = new ImplicitScalar();
        $source->status = 'active';
        $source->priority = 2;

        $target = $this->createMapper()->map($source, ImplicitEnum::class);

        $this->assertInstanceOf(ImplicitEnum::class, $target);
        $this->assertSame(StringStatus::Active, $target->status);
        $this->assertSame(IntPriority::High, $target->priority);
    }

    public function testMapEnumWhenMetadataIsReadFromTarget()
    {
        $source = new class {
            public string $status;
            public int $priority;
        };
        $source->status = 'active';
        $source->priority = 2;

        $target = $this->createMapper()->map($source, EnumTarget::class);

        $this->assertInstanceOf(EnumTarget::class, $target);
        $this->assertSame(StringStatus::Active, $target->status);
        $this->assertSame(IntPriority::High, $target->priority);
    }

    public function testExplicitMapEnumTransformerIsNotDuplicated()
    {
        $source = new EnumSource();
        $source->status = StringStatus::Active;
        $source->priority = IntPriority::Low;

        $target = $this->createMapper()->map($source, ScalarTarget::class);

        $this->assertInstanceOf(ScalarTarget::class, $target);
        $this->assertSame('active', $target->status);
    }

    public function testMapSameEnumIsUnchanged()
    {
        $sameEnumTarget = new class {
            #[Map]
            public StringStatus $status;
        };
        $withSourceMetadata = new EnumSource();
        $withSourceMetadata->status = StringStatus::Active;
        $withSourceMetadata->priority = IntPriority::Low;
        $withTargetMetadata = new class {
            public StringStatus $status;
        };
        $withTargetMetadata->status = StringStatus::Active;

        $viaSource = $this->createMapper()->map($withSourceMetadata, $sameEnumTarget::class);
        $viaTarget = $this->createMapper()->map($withTargetMetadata, $sameEnumTarget::class);

        $this->assertSame(StringStatus::Active, $viaSource->status);
        $this->assertSame(StringStatus::Active, $viaTarget->status);
    }

    #[DataProvider('invalidEnumMappingProvider')]
    public function testInvalidEnumMappingThrows(string $exceptionClass, object $withSourceMetadata, ?string $targetForSource, object $withTargetMetadata, string $targetForTarget)
    {
        $this->assertMapThrows($exceptionClass, $withSourceMetadata, $targetForSource);
        $this->assertMapThrows($exceptionClass, $withTargetMetadata, $targetForTarget);
    }

    public static function invalidEnumMappingProvider(): iterable
    {
        yield from self::pureEnumCases();
        yield from self::backingTypeMismatchCases();
        yield from self::invalidValueCases();
        yield from self::enumToEnumCases();
    }

    private static function pureEnumCases(): iterable
    {
        $pureEnumSource = new class {
            #[Map]
            public PureColor $color;
        };
        $pureEnumSource->color = PureColor::Red;
        $pureEnumTarget = new class {
            #[Map]
            public string $color;
        };
        $plainPureEnum = new class {
            public PureColor $color;
        };
        $plainPureEnum->color = PureColor::Red;
        yield 'pure enum to scalar' => [MappingTransformException::class, $pureEnumSource, $pureEnumTarget::class, $plainPureEnum, $pureEnumTarget::class];

        $scalarToPureEnumSource = new class {
            #[Map]
            public string $color;
        };
        $scalarToPureEnumSource->color = 'red';
        $pureEnumTargetForScalar = new class {
            #[Map]
            public PureColor $color;
        };
        $plainScalarColor = new class {
            public string $color;
        };
        $plainScalarColor->color = 'red';
        yield 'scalar to pure enum' => [MappingTransformException::class, $scalarToPureEnumSource, $pureEnumTargetForScalar::class, $plainScalarColor, $pureEnumTargetForScalar::class];
    }

    private static function backingTypeMismatchCases(): iterable
    {
        $stringEnumToInt = new EnumSource();
        $stringEnumToInt->status = StringStatus::Active;
        $stringEnumToInt->priority = IntPriority::Low;
        $stringEnumToIntTarget = new class {
            #[Map]
            public int $status;
        };
        $plainStringEnum = new class {
            public StringStatus $status;
        };
        $plainStringEnum->status = StringStatus::Active;
        yield 'string-backed enum to int' => [MappingTransformException::class, $stringEnumToInt, $stringEnumToIntTarget::class, $plainStringEnum, $stringEnumToIntTarget::class];

        $stringToIntEnumSource = new class {
            #[Map]
            public string $priority;
        };
        $stringToIntEnumSource->priority = 'high';
        $intEnumTarget = new class {
            #[Map]
            public IntPriority $priority;
        };
        $plainStringPriority = new class {
            public string $priority;
        };
        $plainStringPriority->priority = 'high';
        yield 'string to int-backed enum' => [MappingTransformException::class, $stringToIntEnumSource, $intEnumTarget::class, $plainStringPriority, $intEnumTarget::class];

        $intToStringEnumSource = new class {
            #[Map]
            public int $status;
        };
        $intToStringEnumSource->status = 42;
        $stringEnumTarget = new class {
            #[Map]
            public StringStatus $status;
        };
        $plainInt = new class {
            public int $status;
        };
        $plainInt->status = 42;
        yield 'int to string-backed enum' => [MappingTransformException::class, $intToStringEnumSource, $stringEnumTarget::class, $plainInt, $stringEnumTarget::class];

        $intEnumToString = new EnumSource();
        $intEnumToString->status = StringStatus::Active;
        $intEnumToString->priority = IntPriority::High;
        $intEnumToStringTarget = new class {
            #[Map]
            public string $priority;
        };
        $plainIntEnum = new class {
            public IntPriority $priority;
        };
        $plainIntEnum->priority = IntPriority::High;
        yield 'int-backed enum to string' => [MappingTransformException::class, $intEnumToString, $intEnumToStringTarget::class, $plainIntEnum, $intEnumToStringTarget::class];
    }

    private static function invalidValueCases(): iterable
    {
        $invalidScalar = new ScalarSource();
        $invalidScalar->status = 'nonexistent';
        $invalidScalar->priority = 1;
        $plainInvalidScalar = new class {
            public string $status;
            public int $priority;
        };
        $plainInvalidScalar->status = 'nonexistent';
        $plainInvalidScalar->priority = 1;
        yield 'invalid string to string-backed enum' => [MappingTransformException::class, $invalidScalar, null, $plainInvalidScalar, EnumTarget::class];

        $invalidInt = new ScalarSource();
        $invalidInt->status = 'active';
        $invalidInt->priority = 999;
        $plainInvalidInt = new class {
            public string $status;
            public int $priority;
        };
        $plainInvalidInt->status = 'active';
        $plainInvalidInt->priority = 999;
        yield 'invalid int to int-backed enum' => [MappingTransformException::class, $invalidInt, null, $plainInvalidInt, EnumTarget::class];
    }

    private static function enumToEnumCases(): iterable
    {
        $enumToOther = new EnumSource();
        $enumToOther->status = StringStatus::Active;
        $enumToOther->priority = IntPriority::Low;
        $otherEnumTarget = new class {
            #[Map]
            public IntPriority $status;
        };
        $plainEnumToOther = new class {
            public StringStatus $status;
        };
        $plainEnumToOther->status = StringStatus::Active;
        yield 'enum to other enum' => [\TypeError::class, $enumToOther, $otherEnumTarget::class, $plainEnumToOther, $otherEnumTarget::class];
    }

    public function testMapEnumToScalarWithExplicitTarget()
    {
        $source = new class {
            public StringStatus $status;
        };
        $source->status = StringStatus::Active;

        $plainTarget = new class {
            public string $status;
        };

        $target = $this->createMapper()->map($source, $plainTarget::class);

        $this->assertSame('active', $target->status);
    }

    private function createMapper(): ObjectMapper
    {
        return new ObjectMapper(new EnumMappingMetadataFactory(new ReflectionObjectMapperMetadataFactory()));
    }

    private function assertMapThrows(string $exceptionClass, object $source, ?string $targetClass = null): void
    {
        try {
            $this->createMapper()->map($source, $targetClass);
            $this->fail(\sprintf('Expected %s to be thrown.', $exceptionClass));
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
        }
    }
}
