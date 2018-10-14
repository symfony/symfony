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
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToHtml5LocalDateTimeTransformer;

class DateTimeToHtml5LocalDateTimeTransformerTest extends TestCase
{
    public static function assertEquals($expected, $actual, $message = '', $delta = 0, $maxDepth = 10, $canonicalize = false, $ignoreCase = false)
    {
        if ($expected instanceof \DateTime && $actual instanceof \DateTime) {
            $expected = $expected->format('c');
            $actual = $actual->format('c');
        }

        parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
    }

    public function transformProvider()
    {
        return array(
            array('UTC', 'UTC', '2010-02-03 04:05:06 UTC', '2010-02-03T04:05:06'),
            array('UTC', 'UTC', null, ''),
            array('America/New_York', 'Asia/Hong_Kong', '2010-02-03 04:05:06 America/New_York', '2010-02-03T17:05:06'),
            array('America/New_York', 'Asia/Hong_Kong', null, ''),
            array('UTC', 'Asia/Hong_Kong', '2010-02-03 04:05:06 UTC', '2010-02-03T12:05:06'),
            array('America/New_York', 'UTC', '2010-02-03 04:05:06 America/New_York', '2010-02-03T09:05:06'),
        );
    }

    public function reverseTransformProvider()
    {
        return array(
            // format without seconds, as appears in some browsers
            array('UTC', 'UTC', '2010-02-03 04:05:06 UTC', '2010-02-03T04:05:06'),
            array('UTC', 'UTC', null, ''),
            array('America/New_York', 'Asia/Hong_Kong', '2010-02-03 04:05:06 America/New_York', '2010-02-03T17:05:06'),
            array('America/New_York', 'Asia/Hong_Kong', null, ''),
            array('UTC', 'Asia/Hong_Kong', '2010-02-03 04:05:06 UTC', '2010-02-03T12:05:06'),
            array('America/New_York', 'UTC', '2010-02-03 04:05:06 America/New_York', '2010-02-03T09:05:06'),
            array('UTC', 'UTC', '2010-02-03 04:05:00 UTC', '2010-02-03T04:05'),
            array('America/New_York', 'Asia/Hong_Kong', '2010-02-03 04:05:00 America/New_York', '2010-02-03T17:05'),
            array('Europe/Amsterdam', 'Europe/Amsterdam', '2013-08-21 10:30:00 Europe/Amsterdam', '2013-08-21T10:30:00'),
            array('UTC', 'UTC', '2018-09-15T10:00:00Z', '2018-09-15T10:00:00Z'),
            array('Europe/Berlin', 'Europe/Berlin', '2018-09-15T10:00:00+02:00', '2018-09-15T10:00:00+02:00'),
            array('Europe/Berlin', 'Europe/Berlin', '2018-09-15T10:00:00+0200', '2018-09-15T10:00:00+0200'),
            array('UTC', 'UTC', '2018-10-03T10:00:00.000Z', '2018-10-03T10:00:00.000Z'),
        );
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform($fromTz, $toTz, $from, $to)
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer($fromTz, $toTz);

        $this->assertSame($to, $transformer->transform(null !== $from ? new \DateTime($from) : null));
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransformDateTimeImmutable($fromTz, $toTz, $from, $to)
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer($fromTz, $toTz);

        $this->assertSame($to, $transformer->transform(null !== $from ? new \DateTimeImmutable($from) : null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformRequiresValidDateTime()
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer();
        $transformer->transform('2010-01-01');
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($toTz, $fromTz, $to, $from)
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer($toTz, $fromTz);

        if (null !== $to) {
            $this->assertEquals(new \DateTime($to), $transformer->reverseTransform($from));
        } else {
            $this->assertNull($transformer->reverseTransform($from));
        }
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresString()
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer();
        $transformer->reverseTransform(12345);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNonExistingDate()
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer('UTC', 'UTC');

        $transformer->reverseTransform('2010-04-31T04:05');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsValidDateString()
    {
        $transformer = new DateTimeToHtml5LocalDateTimeTransformer('UTC', 'UTC');

        $transformer->reverseTransform('2010-2010-2010');
    }
}
