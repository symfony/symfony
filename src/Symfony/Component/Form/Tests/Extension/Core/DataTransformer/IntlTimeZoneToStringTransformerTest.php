<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntlTimeZoneToStringTransformer;

/**
 * @requires extension intl
 */
class IntlTimeZoneToStringTransformerTest extends TestCase
{
    public function testSingle()
    {
        $transformer = new IntlTimeZoneToStringTransformer();

        self::assertNull($transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));

        self::assertSame('Europe/Amsterdam', $transformer->transform(\IntlTimeZone::createTimeZone('Europe/Amsterdam')));
        self::assertEquals(\IntlTimeZone::createTimeZone('Europe/Amsterdam'), $transformer->reverseTransform('Europe/Amsterdam'));
    }

    public function testMultiple()
    {
        $transformer = new IntlTimeZoneToStringTransformer(true);

        self::assertNull($transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));

        self::assertSame(['Europe/Amsterdam'], $transformer->transform([\IntlTimeZone::createTimeZone('Europe/Amsterdam')]));
        self::assertEquals([\IntlTimeZone::createTimeZone('Europe/Amsterdam')], $transformer->reverseTransform(['Europe/Amsterdam']));
    }

    public function testInvalidTimezone()
    {
        self::expectException(TransformationFailedException::class);
        (new IntlTimeZoneToStringTransformer())->transform(1);
    }

    public function testUnknownTimezone()
    {
        self::expectException(TransformationFailedException::class);
        (new IntlTimeZoneToStringTransformer(true))->reverseTransform(['Foo/Bar']);
    }
}
