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
use Symfony\Component\Form\Extension\Core\DataTransformer\IntlTimeZoneToStringTransformer;

/**
 * @requires extension intl
 */
class IntlTimeZoneToStringTransformerTest extends TestCase
{
    public function testSingle()
    {
        $transformer = new IntlTimeZoneToStringTransformer();

        $this->assertNull($transformer->transform(null));
        $this->assertNull($transformer->reverseTransform(null));

        $this->assertSame('Europe/Amsterdam', $transformer->transform(\IntlTimeZone::createTimeZone('Europe/Amsterdam')));
        $this->assertEquals(\IntlTimeZone::createTimeZone('Europe/Amsterdam'), $transformer->reverseTransform('Europe/Amsterdam'));
    }

    public function testMultiple()
    {
        $transformer = new IntlTimeZoneToStringTransformer(true);

        $this->assertNull($transformer->transform(null));
        $this->assertNull($transformer->reverseTransform(null));

        $this->assertSame(['Europe/Amsterdam'], $transformer->transform([\IntlTimeZone::createTimeZone('Europe/Amsterdam')]));
        $this->assertEquals([\IntlTimeZone::createTimeZone('Europe/Amsterdam')], $transformer->reverseTransform(['Europe/Amsterdam']));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testInvalidTimezone()
    {
        (new IntlTimeZoneToStringTransformer())->transform(1);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testUnknownTimezone()
    {
        (new IntlTimeZoneToStringTransformer(true))->reverseTransform(['Foo/Bar']);
    }
}
