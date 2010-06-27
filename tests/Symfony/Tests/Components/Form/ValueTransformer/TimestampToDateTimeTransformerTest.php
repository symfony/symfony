<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

require_once __DIR__ . '/../DateTimeTestCase.php';

use Symfony\Components\Form\ValueTransformer\TimestampToDateTimeTransformer;
use Symfony\Tests\Components\Form\DateTimeTestCase;

class TimestampToDateTimeTransformerTest extends DateTimeTestCase
{
    public function testTransform()
    {
        $transformer = new TimestampToDateTimeTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $input = $output->format('U');

        $this->assertDateTimeEquals($output, $transformer->transform($input));
    }

    public function testTransform_differentTimezones()
    {
        $transformer = new TimestampToDateTimeTransformer(array(
            'input_timezone' => 'Asia/Hong_Kong',
            'output_timezone' => 'America/New_York',
        ));

        $output = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $input = $output->format('U');
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($output, $transformer->transform($input));
    }

    public function testReverseTransformExpectsValidTimestamp()
    {
        $transformer = new TimestampToDateTimeTransformer();

        $this->setExpectedException('\InvalidArgumentException');
        $transformer->transform('2010-2010-2010');
    }

    public function testReverseTransform()
    {
        $transformer = new TimestampToDateTimeTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');
        $output = $input->format('U');

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransform_differentTimezones()
    {
        $transformer = new TimestampToDateTimeTransformer(array(
            'input_timezone' => 'Asia/Hong_Kong',
            'output_timezone' => 'America/New_York',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output = $input->format('U');
        $input->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformFromDifferentTimezone()
    {
        $transformer = new TimestampToDateTimeTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'Asia/Hong_Kong',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');

        $dateTime = clone $input;
        $dateTime->setTimezone(new \DateTimeZone('UTC'));
        $output = $dateTime->format('U');

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsDateTime()
    {
        $transformer = new TimestampToDateTimeTransformer();

        $this->setExpectedException('\InvalidArgumentException');
        $transformer->reverseTransform('1234');
    }
}
