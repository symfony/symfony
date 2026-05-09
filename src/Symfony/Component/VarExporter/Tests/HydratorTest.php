<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\Hydrator;
use Symfony\Component\VarExporter\Instantiator;

#[Group('legacy')]
#[IgnoreDeprecations]
class HydratorTest extends TestCase
{
    public function testHydrateInitializedReadonlyPropertySameValueIsIdempotent()
    {
        $object = new HydratorTestClass(123);

        Hydrator::hydrate($object, [
            'value' => 123,
            'status' => 'hydrated',
        ]);

        $this->assertSame(123, $object->getValue());
        $this->assertSame('hydrated', $object->status);
    }

    public function testHydrateInitializedReadonlyPropertyDifferentValueThrows()
    {
        $object = new HydratorTestClass(123);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        Hydrator::hydrate($object, ['value' => 456]);
    }

    public function testHydrateUninitializedReadonlyProperty()
    {
        $object = Instantiator::instantiate(HydratorTestClass::class);

        Hydrator::hydrate($object, ['value' => 456]);

        $this->assertSame(456, $object->getValue());
    }

    public function testHydrateUninitializedReadonlyPropertyAfterHydratorPrimedReflector()
    {
        Hydrator::hydrate(new HydratorTestClass(123), [
            'status' => 'hydrated',
        ]);

        $object = Instantiator::instantiate(HydratorTestClass::class);

        Hydrator::hydrate($object, ['value' => 456]);

        $this->assertSame(456, $object->getValue());
    }
}

class HydratorTestClass
{
    public string $status = 'new';

    private readonly int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
