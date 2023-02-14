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

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;

class DateTimeToStringTransformerTest extends BaseDateTimeTransformerTestCase
{
    public static function dataProvider(): array
    {
        return [
            ['Y-m-d H:i:s', '2010-02-03 16:05:06', '2010-02-03 16:05:06 UTC'],
            ['Y-m-d H:i:00', '2010-02-03 16:05:00', '2010-02-03 16:05:00 UTC'],
            ['Y-m-d H:i', '2010-02-03 16:05', '2010-02-03 16:05:00 UTC'],
            ['Y-m-d H', '2010-02-03 16', '2010-02-03 16:00:00 UTC'],
            ['Y-m-d', '2010-02-03', '2010-02-03 00:00:00 UTC'],
            ['Y-m', '2010-12', '2010-12-01 00:00:00 UTC'],
            ['Y', '2010', '2010-01-01 00:00:00 UTC'],
            ['d-m-Y', '03-02-2010', '2010-02-03 00:00:00 UTC'],
            ['H:i:s', '16:05:06', '1970-01-01 16:05:06 UTC'],
            ['H:i:00', '16:05:00', '1970-01-01 16:05:00 UTC'],
            ['H:i', '16:05', '1970-01-01 16:05:00 UTC'],
            ['H', '16', '1970-01-01 16:00:00 UTC'],
            ['Y-z', '2010-33', '2010-02-03 00:00:00 UTC'],

            // different day representations
            ['Y-m-j', '2010-02-3', '2010-02-03 00:00:00 UTC'],

            // not bijective
            // this will not work as PHP will use actual date to replace missing info
            // and after change of date will lookup for closest Wednesday
            // i.e. value: 2010-02, PHP value: 2010-02-(today i.e. 20), parsed date: 2010-02-24
            // ['Y-m-D', '2010-02-Wed', '2010-02-03 00:00:00 UTC'],
            // ['Y-m-l', '2010-02-Wednesday', '2010-02-03 00:00:00 UTC'],

            // different month representations
            ['Y-n-d', '2010-2-03', '2010-02-03 00:00:00 UTC'],
            ['Y-M-d', '2010-Feb-03', '2010-02-03 00:00:00 UTC'],
            ['Y-F-d', '2010-February-03', '2010-02-03 00:00:00 UTC'],

            // different year representations
            ['y-m-d', '10-02-03', '2010-02-03 00:00:00 UTC'],

            // different time representations
            ['G:i:s', '16:05:06', '1970-01-01 16:05:06 UTC'],
            ['g:i:s a', '4:05:06 pm', '1970-01-01 16:05:06 UTC'],
            ['h:i:s a', '04:05:06 pm', '1970-01-01 16:05:06 UTC'],

            // seconds since Unix
            ['U', '1265213106', '2010-02-03 16:05:06 UTC'],

            ['Y-z', '2010-33', '2010-02-03 00:00:00 UTC'],
        ];
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

    public function testTransformDateTimeImmutable()
    {
        $transformer = new DateTimeToStringTransformer('Asia/Hong_Kong', 'America/New_York', 'Y-m-d H:i:s');

        $input = new \DateTimeImmutable('2010-02-03 12:05:06 America/New_York');
        $output = $input->format('Y-m-d H:i:s');
        $input = $input->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformExpectsDateTime()
    {
        $transformer = new DateTimeToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->transform('1234');
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReverseTransform($format, $input, $output)
    {
        $reverseTransformer = new DateTimeToStringTransformer('UTC', 'UTC', $format);

        $output = new \DateTime($output);

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
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
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsString()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform(1234);
    }

    public function testReverseTransformExpectsValidDateString()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform('2010-2010-2010');
    }

    public function testReverseTransformWithNonExistingDate()
    {
        $reverseTransformer = new DateTimeToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform('2010-04-31');
    }

    protected function createDateTimeTransformer(string $inputTimezone = null, string $outputTimezone = null): BaseDateTimeTransformer
    {
        return new DateTimeToStringTransformer($inputTimezone, $outputTimezone);
    }
}
