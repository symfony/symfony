<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\ValueTransformer;

require_once __DIR__ . '/../DateTimeTestCase.php';

use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Tests\Component\Form\DateTimeTestCase;

class DateTimeToArrayTransformerTest extends DateTimeTestCase
{
    public function testTransform()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransform_empty()
    {
        $transformer = new DateTimeToArrayTransformer();

        $output = array(
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        );

        $this->assertSame($output, $transformer->transform(null));
    }

    public function testTransform_withFields()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
            'fields' => array('year', 'month', 'minute', 'second'),
        ));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = array(
            'year' => '2010',
            'month' => '2',
            'minute' => '5',
            'second' => '6',
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransform_withPadding()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
            'pad' => true,
        ));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = array(
            'year' => '2010',
            'month' => '02',
            'day' => '03',
            'hour' => '04',
            'minute' => '05',
            'second' => '06',
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransform_differentTimezones()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'America/New_York',
            'output_timezone' => 'Asia/Hong_Kong',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));
        $output = array(
            'year' => (string)(int)$dateTime->format('Y'),
            'month' => (string)(int)$dateTime->format('m'),
            'day' => (string)(int)$dateTime->format('d'),
            'hour' => (string)(int)$dateTime->format('H'),
            'minute' => (string)(int)$dateTime->format('i'),
            'second' => (string)(int)$dateTime->format('s'),
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformRequiresDateTime()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345', null);
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $input = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        );

        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input, null));
    }

    public function testReverseTransform_empty()
    {
        $transformer = new DateTimeToArrayTransformer();

        $input = array(
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        );

        $this->assertSame(null, $transformer->reverseTransform($input, null));
    }

    public function testReverseTransform_null()
    {
        $transformer = new DateTimeToArrayTransformer();

        $this->assertSame(null, $transformer->reverseTransform(null, null));
    }

    public function testReverseTransform_differentTimezones()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'America/New_York',
            'output_timezone' => 'Asia/Hong_Kong',
        ));

        $input = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        );

        $output = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input, null));
    }

    public function testReverseTransformToDifferentTimezone()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'Asia/Hong_Kong',
            'output_timezone' => 'UTC',
        ));

        $input = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        );

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $output->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input, null));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformRequiresArray()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345', null);
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeYear()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '-1',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeMonth()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '-1',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeDay()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '-1',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeHour()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '-1',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeMinute()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '-1',
            'second' => '6',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\ValueTransformer\TransformationFailedException
     */
    public function testReverseTransformWithNegativeSecond()
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '-1',
        ));
    }
}
