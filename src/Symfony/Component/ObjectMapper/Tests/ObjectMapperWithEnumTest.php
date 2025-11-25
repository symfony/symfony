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
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumTargetWithMapAttribute;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumToOtherEnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumToScalarSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumToScalarTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumWithExplicitMapSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\EnumWithExplicitMapTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ExplicitEnumTransformSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\IntEnumToStringTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\IntPriority;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\IntToStringEnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\PureColor;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\PureEnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\PureEnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\SameEnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarSourceWithoutMapAttribute;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarToEnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarToEnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarToPureEnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\ScalarToPureEnumTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StringEnumToIntTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StringStatus;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StringToIntEnumSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StringToIntEnumTarget;

final class ObjectMapperWithEnumTest extends TestCase
{
    public function testMapBackedEnumToScalar()
    {
        $withSourceMetadata = new EnumToScalarSource();
        $withSourceMetadata->status = StringStatus::Active;
        $withSourceMetadata->priority = IntPriority::High;
        $withTargetMetadata = new class { public StringStatus $status; public IntPriority $priority; };
        $withTargetMetadata->status = StringStatus::Active;
        $withTargetMetadata->priority = IntPriority::High;

        $viaSource = (new ObjectMapper())->map($withSourceMetadata);
        $viaTarget = (new ObjectMapper())->map($withTargetMetadata, EnumToScalarTarget::class);

        $this->assertSame('active', $viaSource->status);
        $this->assertSame(2, $viaSource->priority);
        $this->assertSame('active', $viaTarget->status);
        $this->assertSame(2, $viaTarget->priority);
    }

    public function testMapScalarToBackedEnum()
    {
        $withSourceMetadata = new ScalarToEnumSource();
        $withSourceMetadata->status = 'active';
        $withSourceMetadata->priority = 1;
        $withTargetMetadata = new class { public string $status; public int $priority; };
        $withTargetMetadata->status = 'active';
        $withTargetMetadata->priority = 1;

        $viaSource = (new ObjectMapper())->map($withSourceMetadata);
        $viaTarget = (new ObjectMapper())->map($withTargetMetadata, ScalarToEnumTarget::class);

        $this->assertSame(StringStatus::Active, $viaSource->status);
        $this->assertSame(IntPriority::Low, $viaSource->priority);
        $this->assertSame(StringStatus::Active, $viaTarget->status);
        $this->assertSame(IntPriority::Low, $viaTarget->priority);
    }

    public function testMapEnumToScalarWithExplicitMapAttribute()
    {
        $source = new EnumWithExplicitMapSource();
        $source->status = StringStatus::Inactive;

        $target = (new ObjectMapper())->map($source);

        $this->assertInstanceOf(EnumWithExplicitMapTarget::class, $target);
        $this->assertSame('inactive', $target->targetStatus);
    }

    public function testMapEnumWhenMetadataIsReadFromTarget()
    {
        $source = new ScalarSourceWithoutMapAttribute();
        $source->status = 'active';

        $target = (new ObjectMapper())->map($source, EnumTargetWithMapAttribute::class);

        $this->assertInstanceOf(EnumTargetWithMapAttribute::class, $target);
        $this->assertSame(StringStatus::Active, $target->status);
    }

    public function testExplicitMapEnumTransformerIsNotDuplicated()
    {
        $source = new ExplicitEnumTransformSource();
        $source->status = StringStatus::Active;

        $target = (new ObjectMapper())->map($source, EnumToScalarTarget::class);

        $this->assertInstanceOf(EnumToScalarTarget::class, $target);
        $this->assertSame('active', $target->status);
    }

    public function testMapSameEnumIsUnchanged()
    {
        $withSourceMetadata = new EnumToScalarSource();
        $withSourceMetadata->status = StringStatus::Active;
        $withSourceMetadata->priority = IntPriority::Low;
        $withTargetMetadata = new class { public StringStatus $status; };
        $withTargetMetadata->status = StringStatus::Active;

        $viaSource = (new ObjectMapper())->map($withSourceMetadata, SameEnumTarget::class);
        $viaTarget = (new ObjectMapper())->map($withTargetMetadata, SameEnumTarget::class);

        $this->assertSame(StringStatus::Active, $viaSource->status);
        $this->assertSame(StringStatus::Active, $viaTarget->status);
    }

    /**
     * @dataProvider invalidEnumMappingProvider
     */
    public function testInvalidEnumMappingThrows(string $exceptionClass, object $withSourceMetadata, ?string $targetForSource, object $withTargetMetadata, string $targetForTarget): void
    {
        $this->assertMapThrows($exceptionClass, $withSourceMetadata, $targetForSource);
        $this->assertMapThrows($exceptionClass, $withTargetMetadata, $targetForTarget);
    }

    public static function invalidEnumMappingProvider(): iterable
    {
        $pureEnumSource = new PureEnumSource();
        $pureEnumSource->color = PureColor::Red;
        $plainPureEnum = new class { public PureColor $color; };
        $plainPureEnum->color = PureColor::Red;
        yield 'pure enum to scalar' => [MappingTransformException::class, $pureEnumSource, null, $plainPureEnum, PureEnumTarget::class];

        $scalarToPureEnum = new ScalarToPureEnumSource();
        $scalarToPureEnum->color = 'red';
        $plainScalarColor = new class { public string $color; };
        $plainScalarColor->color = 'red';
        yield 'scalar to pure enum' => [MappingTransformException::class, $scalarToPureEnum, null, $plainScalarColor, ScalarToPureEnumTarget::class];

        $invalidScalar = new ScalarToEnumSource();
        $invalidScalar->status = 'nonexistent';
        $invalidScalar->priority = 1;
        $plainInvalidScalar = new class { public string $status; public int $priority; };
        $plainInvalidScalar->status = 'nonexistent';
        $plainInvalidScalar->priority = 1;
        yield 'invalid scalar to backed enum' => [MappingTransformException::class, $invalidScalar, null, $plainInvalidScalar, ScalarToEnumTarget::class];

        $stringEnumToInt = new EnumToScalarSource();
        $stringEnumToInt->status = StringStatus::Active;
        $stringEnumToInt->priority = IntPriority::Low;
        $plainStringEnum = new class { public StringStatus $status; };
        $plainStringEnum->status = StringStatus::Active;
        yield 'string-backed enum to int' => [MappingTransformException::class, $stringEnumToInt, StringEnumToIntTarget::class, $plainStringEnum, StringEnumToIntTarget::class];

        $stringToIntEnum = new StringToIntEnumSource();
        $stringToIntEnum->priority = 'high';
        $plainStringPriority = new class { public string $priority; };
        $plainStringPriority->priority = 'high';
        yield 'string to int-backed enum' => [MappingTransformException::class, $stringToIntEnum, null, $plainStringPriority, StringToIntEnumTarget::class];

        $intToStringEnum = new IntToStringEnumSource();
        $intToStringEnum->status = 42;
        $plainInt = new class { public int $status; };
        $plainInt->status = 42;
        yield 'int to string-backed enum' => [MappingTransformException::class, $intToStringEnum, SameEnumTarget::class, $plainInt, SameEnumTarget::class];

        $intEnumToString = new EnumToScalarSource();
        $intEnumToString->status = StringStatus::Active;
        $intEnumToString->priority = IntPriority::High;
        $plainIntEnum = new class { public IntPriority $priority; };
        $plainIntEnum->priority = IntPriority::High;
        yield 'int-backed enum to string' => [MappingTransformException::class, $intEnumToString, IntEnumToStringTarget::class, $plainIntEnum, IntEnumToStringTarget::class];

        $enumToOther = new EnumToScalarSource();
        $enumToOther->status = StringStatus::Active;
        $enumToOther->priority = IntPriority::Low;
        $plainEnumToOther = new class { public StringStatus $status; };
        $plainEnumToOther->status = StringStatus::Active;
        yield 'enum to other enum' => [\TypeError::class, $enumToOther, EnumToOtherEnumTarget::class, $plainEnumToOther, EnumToOtherEnumTarget::class];
    }

    private function assertMapThrows(string $exceptionClass, object $source, ?string $targetClass = null): void
    {
        try {
            (new ObjectMapper())->map($source, $targetClass);
            $this->fail(\sprintf('Expected %s to be thrown.', $exceptionClass));
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
        }
    }
}
