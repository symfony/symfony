<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

require_once __DIR__ . '/../DateTimeTestCase.php';

use Symfony\Components\Form\ValueTransformer\StringToDateTimeTransformer;
use Symfony\Tests\Components\Form\DateTimeTestCase;

class StringToDateTimeTransformerTest extends DateTimeTestCase
{
    public function testTransform()
    {
        $transformer = new StringToDateTimeTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $input = $output->format('Y-m-d H:i:s');

        $this->assertDateTimeEquals($output, $transformer->transform($input));
    }

    public function testTransform_differentTimezones()
    {
        $transformer = new StringToDateTimeTransformer(array(
            'input_timezone' => 'America/New_York',
            'output_timezone' => 'Asia/Hong_Kong',
        ));

        $output = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $input = $output->format('Y-m-d H:i:s');
        $output->setTimeZone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertDateTimeEquals($output, $transformer->transform($input));
    }

    public function testTransformExpectsValidString()
    {
        $transformer = new StringToDateTimeTransformer();

        $this->setExpectedException('\InvalidArgumentException');
        $transformer->transform('2010-2010-2010');
    }

    public function testReverseTransform()
    {
        $transformer = new StringToDateTimeTransformer(array(
            'input_timezone' => 'UTC',
            'output_timezone' => 'UTC',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');
        $output = clone $input;
        $output->setTimezone(new \DateTimeZone('UTC'));
        $output = $output->format('Y-m-d H:i:s');

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransform_differentTimezones()
    {
        $transformer = new StringToDateTimeTransformer(array(
            'input_timezone' => 'Asia/Hong_Kong',
            'output_timezone' => 'America/New_York',
        ));

        $input = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output = $input->format('Y-m-d H:i:s');
        $input->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsDateTime()
    {
        $transformer = new StringToDateTimeTransformer();

        $this->setExpectedException('\InvalidArgumentException');
        $transformer->reverseTransform('1234');
    }
}
