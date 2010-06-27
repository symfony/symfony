<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

require_once __DIR__ . '/../DateTimeTestCase.php';

use Symfony\Components\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Tests\Components\Form\DateTimeTestCase;

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
            'year' => 2010,
            'month' => 2,
            'day' => 3,
            'hour' => 4,
            'minute' => 5,
            'second' => 6,
        );

        $this->assertSame($output, $transformer->transform($input));
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
            'year' => 2010,
            'month' => 2,
            'minute' => 5,
            'second' => 6,
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
            'year' => (int)$dateTime->format('Y'),
            'month' => (int)$dateTime->format('m'),
            'day' => (int)$dateTime->format('d'),
            'hour' => (int)$dateTime->format('H'),
            'minute' => (int)$dateTime->format('i'),
            'second' => (int)$dateTime->format('s'),
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformRequiresDateTime()
    {
        $transformer = new DateTimeToArrayTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->reverseTransform('12345');
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $input = array(
            'year' => 2010,
            'month' => 2,
            'day' => 3,
            'hour' => 4,
            'minute' => 5,
            'second' => 6,
        );

        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransform_differentTimezones()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'America/New_York',
            'output_timezone' => 'Asia/Hong_Kong',
        ));

        $input = array(
            'year' => 2010,
            'month' => 2,
            'day' => 3,
            'hour' => 4,
            'minute' => 5,
            'second' => 6,
        );

        $output = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformToDifferentTimezone()
    {
        $transformer = new DateTimeToArrayTransformer(array(
            'input_timezone' => 'Asia/Hong_Kong',
            'output_timezone' => 'UTC',
        ));

        $input = array(
            'year' => 2010,
            'month' => 2,
            'day' => 3,
            'hour' => 4,
            'minute' => 5,
            'second' => 6,
        );

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $output->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformRequiresArray()
    {
        $transformer = new DateTimeToArrayTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->reverseTransform('12345');
    }
}
