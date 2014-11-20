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

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;

class DateTimeToStringTransformerTest extends DateTimeTestCase
{
    public function dataProvider()
    {
        $data = array(
            array('Y-m-d H:i:s', '2010-02-03 16:05:06', '2010-02-03 16:05:06 UTC'),
            array('Y-m-d H:i:00', '2010-02-03 16:05:00', '2010-02-03 16:05:00 UTC'),
            array('Y-m-d H:i', '2010-02-03 16:05', '2010-02-03 16:05:00 UTC'),
            array('Y-m-d H', '2010-02-03 16', '2010-02-03 16:00:00 UTC'),
            array('Y-m-d', '2010-02-03', '2010-02-03 00:00:00 UTC'),
            array('Y-m', '2010-12', '2010-12-01 00:00:00 UTC'),
            array('Y', '2010', '2010-01-01 00:00:00 UTC'),
            array('d-m-Y', '03-02-2010', '2010-02-03 00:00:00 UTC'),
            array('H:i:s', '16:05:06', '1970-01-01 16:05:06 UTC'),
            array('H:i:00', '16:05:00', '1970-01-01 16:05:00 UTC'),
            array('H:i', '16:05', '1970-01-01 16:05:00 UTC'),
            array('H', '16', '1970-01-01 16:00:00 UTC'),

            // different day representations
            array('Y-m-j', '2010-02-3', '2010-02-03 00:00:00 UTC'),
            array('z', '33', '1970-02-03 00:00:00 UTC'),

            // not bijective
            // this will not work as PHP will use actual date to replace missing info
            // and after change of date will lookup for closest Wednesday
            // i.e. value: 2010-02, PHP value: 2010-02-(today i.e. 20), parsed date: 2010-02-24
            //array('Y-m-D', '2010-02-Wed', '2010-02-03 00:00:00 UTC'),
            //array('Y-m-l', '2010-02-Wednesday', '2010-02-03 00:00:00 UTC'),

            // different month representations
            array('Y-n-d', '2010-2-03', '2010-02-03 00:00:00 UTC'),
            array('Y-M-d', '2010-Feb-03', '2010-02-03 00:00:00 UTC'),
            array('Y-F-d', '2010-February-03', '2010-02-03 00:00:00 UTC'),

            // different year representations
            array('y-m-d', '10-02-03', '2010-02-03 00:00:00 UTC'),

            // different time representations
            array('G:i:s', '16:05:06', '1970-01-01 16:05:06 UTC'),
            array('g:i:s a', '4:05:06 pm', '1970-01-01 16:05:06 UTC'),
            array('h:i:s a', '04:05:06 pm', '1970-01-01 16:05:06 UTC'),

            // seconds since Unix
            array('U', '1265213106', '2010-02-03 16:05:06 UTC'),
        );

        // This test will fail < 5.3.9 - see https://bugs.php.net/51994
        if (PHP_VERSION_ID >= 50309) {
            $data[] = array('Y-z', '2010-33', '2010-02-03 00:00:00 UTC');
        }

        return $data;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testTransform($format, $output, $input)
    {
        $transformer = new DateTimeToStringTransformer('UTC', 'UTC', $format);

        $input = new \DateTime($input);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeToStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testTransformWithDifferentTimezones()
    {
        $transformer = new DateTimeToStringTransformer('Asia/Hong_Kong', 'America/New_York', 'Y-m-d H:i:s');

        $input = new \DateTime('2010-02-03 12:05:06 America/New_York');
        $output = $input->format('Y-m-d H:i:s');
        $input->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformExpectsDateTime()
    {
        $transformer = new DateTimeToStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->transform('1234');
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReverseTransformUsingPipe($format, $input, $output)
    {
        if (PHP_VERSION_ID < 50307) {
            $this->markTestSkipped('Pipe usage requires PHP 5.3.7 or newer.');
        }

        $reverseTransformer = new DateTimeToStringTransformer('UTC', 'UTC', $format, true);

        $output = new \DateTime($output);

        $this->assertDateTimeEquals($output, $reverseTransformer->reverseTransform($input));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReverseTransformWithoutUsingPipe($format, $input, $output)
    {
        $reverseTransformer = new DateTimeToStringTransformer('UTC', 'UTC', $format, false);

        $output = new \DateTime($output);

        $this->assertDateTimeEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->assertNull($reverseTransformer->reverseTransform(''));
    }

    public function testReverseTransformWithDifferentTimezones()
    {
        $reverseTransformer = new DateTimeToStringTransformer('America/New_York', 'Asia/Hong_Kong', 'Y-m-d H:i:s');

        $output = new \DateTime('2010-02-03 16:05:06 Asia/Hong_Kong');
        $input = $output->format('Y-m-d H:i:s');
        $output->setTimeZone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsString()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $reverseTransformer->reverseTransform(1234);
    }

    public function testReverseTransformExpectsValidDateString()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $reverseTransformer->reverseTransform('2010-2010-2010');
    }

    public function testReverseTransformWithNonExistingDate()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $reverseTransformer->reverseTransform('2010-04-31');
    }
}
