<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\PropertyInfo\Util\LegacyTypeConverter;
use Symfony\Component\TypeInfo\Type;

class LegacyTypeConverterTest extends TestCase
{
    public function testFromLegacyConvertsProvidedLegacyTypes()
    {
        $this->assertEquals(
            Type::string(),
            LegacyTypeConverter::fromLegacy(static fn () => [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]),
        );
    }

    public function testFromLegacyReturnsNullWhenProviderReturnsNull()
    {
        $this->assertNull(LegacyTypeConverter::fromLegacy(static fn () => null));
    }

    public function testFromLegacySilencesLegacyTypeClassDeprecation()
    {
        $deprecations = [];
        $prev = set_error_handler(static function ($type, $message) use (&$deprecations) {
            if (\E_USER_DEPRECATED === $type) {
                $deprecations[] = $message;
            }

            return true;
        });

        try {
            LegacyTypeConverter::fromLegacy(static fn () => [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public function testFromLegacyForwardsUnrelatedDeprecations()
    {
        $deprecations = [];
        $prev = set_error_handler(static function ($type, $message) use (&$deprecations) {
            if (\E_USER_DEPRECATED === $type) {
                $deprecations[] = $message;
            }

            return true;
        });

        try {
            LegacyTypeConverter::fromLegacy(static function () {
                @trigger_error('Some unrelated deprecation.', \E_USER_DEPRECATED);

                return [new LegacyType(LegacyType::BUILTIN_TYPE_INT)];
            });
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['Some unrelated deprecation.'], $deprecations);
    }
}
