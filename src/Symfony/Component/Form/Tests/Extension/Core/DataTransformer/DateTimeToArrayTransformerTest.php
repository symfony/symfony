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

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;

class DateTimeToArrayTransformerTest extends DateTimeTestCase
{
    public function testTransform(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

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

    public function testTransformEmpty(): void
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

    public function testTransformEmptyWithFields(): void
    {
        $transformer = new DateTimeToArrayTransformer(null, null, array('year', 'minute', 'second'));

        $output = array(
            'year' => '',
            'minute' => '',
            'second' => '',
        );

        $this->assertSame($output, $transformer->transform(null));
    }

    public function testTransformWithFields(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', array('year', 'month', 'minute', 'second'));

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = array(
            'year' => '2010',
            'month' => '2',
            'minute' => '5',
            'second' => '6',
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformWithPadding(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', null, true);

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

    public function testTransformDifferentTimezones(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));
        $output = array(
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformDateTimeImmutable(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');
        $dateTime = $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));
        $output = array(
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        );

        $this->assertSame($output, $transformer->transform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformRequiresDateTime(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransform(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        );

        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformWithSomeZero(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '0',
            'second' => '0',
        );

        $output = new \DateTime('2010-02-03 04:00:00 UTC');

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty(): void
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

        $this->assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmptySubsetOfFields(): void
    {
        $transformer = new DateTimeToArrayTransformer(null, null, array('year', 'month', 'day'));

        $input = array(
            'year' => '',
            'month' => '',
            'day' => '',
        );

        $this->assertNull($transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptyYear(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptyMonth(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptyDay(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptyHour(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptyMinute(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyEmptySecond(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
        ));
    }

    public function testReverseTransformNull(): void
    {
        $transformer = new DateTimeToArrayTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformDifferentTimezones(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

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

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformToDifferentTimezone(): void
    {
        $transformer = new DateTimeToArrayTransformer('Asia/Hong_Kong', 'UTC');

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

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArray(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeYear(): void
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
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeMonth(): void
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
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeDay(): void
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
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeHour(): void
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
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeMinute(): void
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
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNegativeSecond(): void
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

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithInvalidMonth(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '13',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithInvalidDay(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithStringDay(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => 'bazinga',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithStringMonth(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => 'bazinga',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithStringYear(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => 'bazinga',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithEmptyStringHour(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '',
            'minute' => '5',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithEmptyStringMinute(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '',
            'second' => '6',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithEmptyStringSecond(): void
    {
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform(array(
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '',
        ));
    }
}
