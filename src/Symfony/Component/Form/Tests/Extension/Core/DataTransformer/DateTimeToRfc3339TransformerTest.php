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

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToRfc3339Transformer;

class DateTimeToRfc3339TransformerTest extends DateTimeTestCase
{
    protected $dateTime;
    protected $dateTimeWithoutSeconds;

    protected function setUp()
    {
        parent::setUp();

        $this->dateTime = new \DateTime('2010-02-03 04:05:06 UTC');
        $this->dateTimeWithoutSeconds = new \DateTime('2010-02-03 04:05:00 UTC');
    }

    protected function tearDown()
    {
        $this->dateTime = null;
        $this->dateTimeWithoutSeconds = null;
    }

    public static function assertEquals($expected, $actual, $message = '', $delta = 0, $maxDepth = 10, $canonicalize = false, $ignoreCase = false)
    {
        if ($expected instanceof \DateTime && $actual instanceof \DateTime) {
            $expected = $expected->format('c');
            $actual = $actual->format('c');
        }

        parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
    }

    public function allProvider()
    {
        return array(
            array('UTC', 'UTC', '2010-02-03 04:05:06 UTC', '2010-02-03T04:05:06Z'),
            array('UTC', 'UTC', null, ''),
            array('America/New_York', 'Asia/Hong_Kong', '2010-02-03 04:05:06 America/New_York', '2010-02-03T17:05:06+08:00'),
            array('America/New_York', 'Asia/Hong_Kong', null, ''),
            array('UTC', 'Asia/Hong_Kong', '2010-02-03 04:05:06 UTC', '2010-02-03T12:05:06+08:00'),
            array('America/New_York', 'UTC', '2010-02-03 04:05:06 America/New_York', '2010-02-03T09:05:06Z'),
        );
    }

    public function transformProvider()
    {
        return $this->allProvider();
    }

    public function reverseTransformProvider()
    {
        return array_merge($this->allProvider(), array(
            // format without seconds, as appears in some browsers
            array('UTC', 'UTC', '2010-02-03 04:05:00 UTC', '2010-02-03T04:05Z'),
            array('America/New_York', 'Asia/Hong_Kong', '2010-02-03 04:05:00 America/New_York', '2010-02-03T17:05+08:00'),
            array('Europe/Amsterdam', 'Europe/Amsterdam', '2013-08-21 10:30:00 Europe/Amsterdam', '2013-08-21T08:30:00Z')
        ));
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform($fromTz, $toTz, $from, $to)
    {
        $transformer = new DateTimeToRfc3339Transformer($fromTz, $toTz);

        $this->assertSame($to, $transformer->transform(null !== $from ? new \DateTime($from) : null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformRequiresValidDateTime()
    {
        $transformer = new DateTimeToRfc3339Transformer();
        $transformer->transform('2010-01-01');
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($toTz, $fromTz, $to, $from)
    {
        $transformer = new DateTimeToRfc3339Transformer($toTz, $fromTz);

        if (null !== $to) {
            $this->assertDateTimeEquals(new \DateTime($to), $transformer->reverseTransform($from));
        } else {
            $this->assertSame($to, $transformer->reverseTransform($from));
        }
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresString()
    {
        $transformer = new DateTimeToRfc3339Transformer();
        $transformer->reverseTransform(12345);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNonExistingDate()
    {
        $transformer = new DateTimeToRfc3339Transformer('UTC', 'UTC');

        $transformer->reverseTransform('2010-04-31T04:05Z');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsValidDateString()
    {
        $transformer = new DateTimeToRfc3339Transformer('UTC', 'UTC');

        $transformer->reverseTransform('2010-2010-2010');
    }
}
