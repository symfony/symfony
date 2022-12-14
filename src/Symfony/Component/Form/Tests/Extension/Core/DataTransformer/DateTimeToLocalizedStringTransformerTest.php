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
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Tests\Extension\Core\DataTransformer\Traits\DateTimeEqualsTrait;
use Symfony\Component\Intl\Util\IntlTestHelper;

class DateTimeToLocalizedStringTransformerTest extends BaseDateTimeTransformerTestCase
{
    use DateTimeEqualsTrait;

    protected $dateTime;
    protected $dateTimeWithoutSeconds;
    private $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        // Normalize intl. configuration settings.
        if (\extension_loaded('intl')) {
            $this->iniSet('intl.use_exceptions', 0);
            $this->iniSet('intl.error_level', 0);
        }

        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '57.1');

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('de_AT');

        $this->dateTime = new \DateTime('2010-02-03 04:05:06 UTC');
        $this->dateTimeWithoutSeconds = new \DateTime('2010-02-03 04:05:00 UTC');
    }

    protected function tearDown(): void
    {
        $this->dateTime = null;
        $this->dateTimeWithoutSeconds = null;
        \Locale::setDefault($this->defaultLocale);
    }

    public static function dataProvider()
    {
        return [
            [\IntlDateFormatter::SHORT, null, null, '03.02.10, 04:05', '2010-02-03 04:05:00 UTC'],
            [\IntlDateFormatter::MEDIUM, null, null, '03.02.2010, 04:05', '2010-02-03 04:05:00 UTC'],
            [\IntlDateFormatter::LONG, null, null, '3. Februar 2010 um 04:05', '2010-02-03 04:05:00 UTC'],
            [\IntlDateFormatter::FULL, null, null, 'Mittwoch, 3. Februar 2010 um 04:05', '2010-02-03 04:05:00 UTC'],
            [\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, null, '03.02.10', '2010-02-03 00:00:00 UTC'],
            [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, '03.02.2010', '2010-02-03 00:00:00 UTC'],
            [\IntlDateFormatter::LONG, \IntlDateFormatter::NONE, null, '3. Februar 2010', '2010-02-03 00:00:00 UTC'],
            [\IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, 'Mittwoch, 3. Februar 2010', '2010-02-03 00:00:00 UTC'],
            [null, \IntlDateFormatter::SHORT, null, '03.02.2010, 04:05', '2010-02-03 04:05:00 UTC'],
            [null, \IntlDateFormatter::MEDIUM, null, '03.02.2010, 04:05:06', '2010-02-03 04:05:06 UTC'],
            [null, \IntlDateFormatter::LONG, null, '03.02.2010, 04:05:06 UTC', '2010-02-03 04:05:06 UTC'],
            [null, \IntlDateFormatter::LONG, null, '03.02.2010, 04:05:06 UTC', '2010-02-03 04:05:06 GMT'],
            // see below for extra test case for time format FULL
            [\IntlDateFormatter::NONE, \IntlDateFormatter::SHORT, null, '04:05', '1970-01-01 04:05:00 UTC'],
            [\IntlDateFormatter::NONE, \IntlDateFormatter::MEDIUM, null, '04:05:06', '1970-01-01 04:05:06 UTC'],
            [\IntlDateFormatter::NONE, \IntlDateFormatter::LONG, null, '04:05:06 UTC', '1970-01-01 04:05:06 GMT'],
            [\IntlDateFormatter::NONE, \IntlDateFormatter::LONG, null, '04:05:06 UTC', '1970-01-01 04:05:06 UTC'],
            [null, null, 'yyyy-MM-dd HH:mm:00', '2010-02-03 04:05:00', '2010-02-03 04:05:00 UTC'],
            [null, null, 'yyyy-MM-dd HH:mm', '2010-02-03 04:05', '2010-02-03 04:05:00 UTC'],
            [null, null, 'yyyy-MM-dd HH', '2010-02-03 04', '2010-02-03 04:00:00 UTC'],
            [null, null, 'yyyy-MM-dd', '2010-02-03', '2010-02-03 00:00:00 UTC'],
            [null, null, 'yyyy-MM', '2010-02', '2010-02-01 00:00:00 UTC'],
            [null, null, 'yyyy', '2010', '2010-01-01 00:00:00 UTC'],
            [null, null, 'dd-MM-yyyy', '03-02-2010', '2010-02-03 00:00:00 UTC'],
            [null, null, 'HH:mm:ss', '04:05:06', '1970-01-01 04:05:06 UTC'],
            [null, null, 'HH:mm:00', '04:05:00', '1970-01-01 04:05:00 UTC'],
            [null, null, 'HH:mm', '04:05', '1970-01-01 04:05:00 UTC'],
            [null, null, 'HH', '04', '1970-01-01 04:00:00 UTC'],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testTransform($dateFormat, $timeFormat, $pattern, $output, $input)
    {
        IntlTestHelper::requireFullIntl($this, '59.1');
        \Locale::setDefault('de_AT');

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
        IntlTestHelper::requireFullIntl($this, '59.1');
        \Locale::setDefault('de_AT');

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::FULL);

        $this->assertEquals('03.02.2010, 04:05:06 Koordinierte Weltzeit', $transformer->transform($this->dateTime));
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

        $this->assertDateTimeEquals(
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

    public function testTransformDateTimeImmutableTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');

        $dateTime = clone $input;
        $dateTime = $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($dateTime->format('d.m.Y, H:i'), $transformer->transform($input));
    }

    public function testTransformRequiresValidDateTime()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->transform('2010-01-01');
    }

    public function testTransformWrapsIntlErrors()
    {
        $this->markTestIncomplete('Checking for intl errors needs to be reimplemented');

        $transformer = new DateTimeToLocalizedStringTransformer();

        // HOW TO REPRODUCE?

        // $this->expectException(\Symfony\Component\Form\Extension\Core\DataTransformer\TransformationFailedException::class);

        // $transformer->transform(1.5);
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

        $this->assertDateTimeEquals($output, $transformer->reverseTransform($input));
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

    public function testReverseTransformRequiresString()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform(12345);
    }

    public function testReverseTransformWrapsIntlErrors()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransformWithNonExistingDate()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::SHORT);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('31.04.10 04:05'));
    }

    public function testReverseTransformOutOfTimestampRange()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');
        $transformer->reverseTransform('1789-07-14');
    }

    public function testReverseTransformFiveDigitYears()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, null, \IntlDateFormatter::GREGORIAN, 'yyyy-MM-dd');
        $transformer->reverseTransform('20107-03-21');
    }

    public function testReverseTransformFiveDigitYearsWithTimestamp()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, null, \IntlDateFormatter::GREGORIAN, 'yyyy-MM-dd HH:mm:ss');
        $transformer->reverseTransform('20107-03-21 12:34:56');
    }

    public function testReverseTransformWrapsIntlErrorsWithErrorLevel()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is not loaded');
        }

        $this->iniSet('intl.error_level', \E_WARNING);

        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransformWrapsIntlErrorsWithExceptions()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is not loaded');
        }

        $this->iniSet('intl.use_exceptions', 1);

        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransformWrapsIntlErrorsWithExceptionsAndErrorLevel()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is not loaded');
        }

        $this->iniSet('intl.use_exceptions', 1);
        $this->iniSet('intl.error_level', \E_WARNING);

        $this->expectException(TransformationFailedException::class);
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    protected function createDateTimeTransformer(string $inputTimezone = null, string $outputTimezone = null): BaseDateTimeTransformer
    {
        return new DateTimeToLocalizedStringTransformer($inputTimezone, $outputTimezone);
    }
}
