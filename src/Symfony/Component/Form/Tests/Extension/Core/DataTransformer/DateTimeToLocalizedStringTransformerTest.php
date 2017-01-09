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

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class DateTimeToLocalizedStringTransformerTest extends DateTimeTestCase
{
    protected $dateTime;
    protected $dateTimeWithoutSeconds;

    protected function setUp()
    {
        parent::setUp();

        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

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

    public function dataProvider()
    {
        return array(
            array(\IntlDateFormatter::SHORT, null, null, '03.02.10, 04:05', '2010-02-03 04:05:00 UTC'),
            array(\IntlDateFormatter::MEDIUM, null, null, '03.02.2010, 04:05', '2010-02-03 04:05:00 UTC'),
            array(\IntlDateFormatter::LONG, null, null, '3. Februar 2010 um 04:05', '2010-02-03 04:05:00 UTC'),
            array(\IntlDateFormatter::FULL, null, null, 'Mittwoch, 3. Februar 2010 um 04:05', '2010-02-03 04:05:00 UTC'),
            array(\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, null, '03.02.10', '2010-02-03 00:00:00 UTC'),
            array(\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, '03.02.2010', '2010-02-03 00:00:00 UTC'),
            array(\IntlDateFormatter::LONG, \IntlDateFormatter::NONE, null, '3. Februar 2010', '2010-02-03 00:00:00 UTC'),
            array(\IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, 'Mittwoch, 3. Februar 2010', '2010-02-03 00:00:00 UTC'),
            array(null, \IntlDateFormatter::SHORT, null, '03.02.2010, 04:05', '2010-02-03 04:05:00 UTC'),
            array(null, \IntlDateFormatter::MEDIUM, null, '03.02.2010, 04:05:06', '2010-02-03 04:05:06 UTC'),
            array(null, \IntlDateFormatter::LONG, null, '03.02.2010, 04:05:06 GMT', '2010-02-03 04:05:06 UTC'),
            // see below for extra test case for time format FULL
            array(\IntlDateFormatter::NONE, \IntlDateFormatter::SHORT, null, '04:05', '1970-01-01 04:05:00 UTC'),
            array(\IntlDateFormatter::NONE, \IntlDateFormatter::MEDIUM, null, '04:05:06', '1970-01-01 04:05:06 UTC'),
            array(\IntlDateFormatter::NONE, \IntlDateFormatter::LONG, null, '04:05:06 GMT', '1970-01-01 04:05:06 UTC'),
            array(null, null, 'yyyy-MM-dd HH:mm:00', '2010-02-03 04:05:00', '2010-02-03 04:05:00 UTC'),
            array(null, null, 'yyyy-MM-dd HH:mm', '2010-02-03 04:05', '2010-02-03 04:05:00 UTC'),
            array(null, null, 'yyyy-MM-dd HH', '2010-02-03 04', '2010-02-03 04:00:00 UTC'),
            array(null, null, 'yyyy-MM-dd', '2010-02-03', '2010-02-03 00:00:00 UTC'),
            array(null, null, 'yyyy-MM', '2010-02', '2010-02-01 00:00:00 UTC'),
            array(null, null, 'yyyy', '2010', '2010-01-01 00:00:00 UTC'),
            array(null, null, 'dd-MM-yyyy', '03-02-2010', '2010-02-03 00:00:00 UTC'),
            array(null, null, 'HH:mm:ss', '04:05:06', '1970-01-01 04:05:06 UTC'),
            array(null, null, 'HH:mm:00', '04:05:00', '1970-01-01 04:05:00 UTC'),
            array(null, null, 'HH:mm', '04:05', '1970-01-01 04:05:00 UTC'),
            array(null, null, 'HH', '04', '1970-01-01 04:00:00 UTC'),
        );
    }

    /**
     * @dataProvider dataProvider
     */
    public function testTransform($dateFormat, $timeFormat, $pattern, $output, $input)
    {
        $transformer = new DateTimeToLocalizedStringTransformer(
            'UTC',
            'UTC',
            $dateFormat,
            $timeFormat,
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );

        $input = new \DateTime($input);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformFullTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::FULL);

        $this->assertEquals('03.02.2010, 04:05:06 GMT', $transformer->transform($this->dateTime));
    }

    public function testTransformToDifferentLocale()
    {
        \Locale::setDefault('en_US');

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');

        $this->assertEquals('Feb 3, 2010, 4:05 AM', $transformer->transform($this->dateTime));
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testTransformWithDifferentTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = clone $input;
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($dateTime->format('d.m.Y, H:i'), $transformer->transform($input));
    }

    public function testReverseTransformWithNoConstructorParameters()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Rome');

        $transformer = new DateTimeToLocalizedStringTransformer();

        $dateTime = new \DateTime('2010-02-03 04:05');

        $this->assertEquals(
            $dateTime->format('c'),
            $transformer->reverseTransform('03.02.2010, 04:05')->format('c')
        );

        date_default_timezone_set($tz);
    }

    public function testTransformWithDifferentPatterns()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'MM*yyyy*dd HH|mm|ss');

        $this->assertEquals('02*2010*03 04|05|06', $transformer->transform($this->dateTime));
    }

    /**
     * @requires PHP 5.5
     */
    public function testTransformDateTimeImmutableTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');

        $dateTime = clone $input;
        $dateTime = $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($dateTime->format('d.m.Y, H:i'), $transformer->transform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformRequiresValidDateTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->transform('2010-01-01');
    }

    public function testTransformWrapsIntlErrors()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        $this->markTestIncomplete('Checking for intl errors needs to be reimplemented');

        // HOW TO REPRODUCE?

        //$this->setExpectedException('Symfony\Component\Form\Extension\Core\DataTransformer\TransformationFailedException');

        //$transformer->transform(1.5);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReverseTransform($dateFormat, $timeFormat, $pattern, $input, $output)
    {
        $transformer = new DateTimeToLocalizedStringTransformer(
            'UTC',
            'UTC',
            $dateFormat,
            $timeFormat,
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );

        $output = new \DateTime($output);

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformFullTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::FULL);

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('03.02.2010, 04:05:06 GMT+00:00'));
    }

    public function testReverseTransformFromDifferentLocale()
    {
        \Locale::setDefault('en_US');

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('Feb 3, 2010, 04:05 AM'));
    }

    public function testReverseTransformWithDifferentTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $dateTime = new \DateTime('2010-02-03 04:05:00 Asia/Hong_Kong');
        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($dateTime, $transformer->reverseTransform('03.02.2010, 04:05'));
    }

    public function testReverseTransformOnlyDateWithDifferentTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('Europe/Berlin', 'Pacific/Tahiti', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'yyyy-MM-dd');

        $dateTime = new \DateTime('2017-01-10 11:00', new \DateTimeZone('Europe/Berlin'));

        $this->assertDateTimeEquals($dateTime, $transformer->reverseTransform('2017-01-10'));
    }

    public function testReverseTransformWithDifferentPatterns()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'MM*yyyy*dd HH|mm|ss');

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('02*2010*03 04|05|06'));
    }

    public function testReverseTransformDateOnlyWithDstIssue()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('Europe/Rome', 'Europe/Rome', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'dd/MM/yyyy');

        $this->assertDateTimeEquals(
            new \DateTime('1978-05-28', new \DateTimeZone('Europe/Rome')),
            $transformer->reverseTransform('28/05/1978')
        );
    }

    public function testReverseTransformDateOnlyWithDstIssueAndEscapedText()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('Europe/Rome', 'Europe/Rome', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, "'day': dd 'month': MM 'year': yyyy");

        $this->assertDateTimeEquals(
            new \DateTime('1978-05-28', new \DateTimeZone('Europe/Rome')),
            $transformer->reverseTransform('day: 28 month: 05 year: 1978')
        );
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresString()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform(12345);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWrapsIntlErrors()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testValidateDateFormatOption()
    {
        new DateTimeToLocalizedStringTransformer(null, null, 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testValidateTimeFormatOption()
    {
        new DateTimeToLocalizedStringTransformer(null, null, null, 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWithNonExistingDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::SHORT);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('31.04.10 04:05'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformOutOfTimestampRange()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');
        $transformer->reverseTransform('1789-07-14');
    }
}
