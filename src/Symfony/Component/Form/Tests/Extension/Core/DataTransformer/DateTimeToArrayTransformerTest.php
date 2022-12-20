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
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;

class DateTimeToArrayTransformerTest extends BaseDateTimeTransformerTest
{
    public function testTransform()
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        self::assertSame($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeToArrayTransformer();

        $output = [
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        ];

        self::assertSame($output, $transformer->transform(null));
    }

    public function testTransformEmptyWithFields()
    {
        $transformer = new DateTimeToArrayTransformer(null, null, ['year', 'minute', 'second']);

        $output = [
            'year' => '',
            'minute' => '',
            'second' => '',
        ];

        self::assertSame($output, $transformer->transform(null));
    }

    public function testTransformWithFields()
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', ['year', 'month', 'minute', 'second']);

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '2',
            'minute' => '5',
            'second' => '6',
        ];

        self::assertSame($output, $transformer->transform($input));
    }

    public function testTransformWithPadding()
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', null, true);

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '02',
            'day' => '03',
            'hour' => '04',
            'minute' => '05',
            'second' => '06',
        ];

        self::assertSame($output, $transformer->transform($input));
    }

    public function testTransformDifferentTimezones()
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));
        $output = [
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        ];

        self::assertSame($output, $transformer->transform($input));
    }

    public function testTransformDateTimeImmutable()
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');
        $dateTime = $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));
        $output = [
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        ];

        self::assertSame($output, $transformer->transform($input));
    }

    public function testTransformRequiresDateTime()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformWithSomeZero()
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '0',
            'second' => '0',
        ];

        $output = new \DateTime('2010-02-03 04:00:00 UTC');

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty()
    {
        $transformer = new DateTimeToArrayTransformer();

        $input = [
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        ];

        self::assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmptySubsetOfFields()
    {
        $transformer = new DateTimeToArrayTransformer(null, null, ['year', 'month', 'day']);

        $input = [
            'year' => '',
            'month' => '',
            'day' => '',
        ];

        self::assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformPartiallyEmptyYear()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyMonth()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyDay()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyHour()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyMinute()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptySecond()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
        ]);
    }

    public function testReverseTransformNull()
    {
        $transformer = new DateTimeToArrayTransformer();

        self::assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformDifferentTimezones()
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformToDifferentTimezone()
    {
        $transformer = new DateTimeToArrayTransformer('Asia/Hong_Kong', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $output->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformRequiresArray()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransformWithNegativeYear()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '-1',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeMonth()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '-1',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeDay()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '-1',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeHour()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '-1',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeMinute()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '-1',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeSecond()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '-1',
        ]);
    }

    public function testReverseTransformWithInvalidMonth()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '13',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithInvalidDay()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringDay()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => 'bazinga',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringMonth()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => 'bazinga',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringYear()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => 'bazinga',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringHour()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringMinute()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringSecond()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '',
        ]);
    }

    protected function createDateTimeTransformer(string $inputTimezone = null, string $outputTimezone = null): BaseDateTimeTransformer
    {
        return new DateTimeToArrayTransformer($inputTimezone, $outputTimezone);
    }
}
