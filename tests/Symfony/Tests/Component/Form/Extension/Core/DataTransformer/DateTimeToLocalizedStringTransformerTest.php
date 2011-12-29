<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;

class DateTimeToLocalizedStringTransformerTest extends DateTimeTestCase
{
    protected $dateTime;
    protected $dateTimeWithoutSeconds;

    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');

        $this->dateTime = new \DateTime('2010-02-03 04:05:06 UTC');
        $this->dateTimeWithoutSeconds = new \DateTime('2010-02-03 04:05:00 UTC');
    }

    protected function tearDown()
    {
        $this->dateTime = null;
        $this->dateTimeWithoutSeconds = null;
    }

    public static function assertEquals($expected, $actual, $message = '', $delta = 0, $maxDepth = 10, $canonicalize = FALSE, $ignoreCase = FALSE)
    {
        if ($expected instanceof \DateTime && $actual instanceof \DateTime) {
            $expected = $expected->format('c');
            $actual = $actual->format('c');
        }

        parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
    }

    public function testTransformShortDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::SHORT);
        $this->assertEquals('03.02.10 04:05', $transformer->transform($this->dateTime));
    }

    public function testTransformMediumDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::MEDIUM);

        $this->assertEquals('03.02.2010 04:05', $transformer->transform($this->dateTime));
    }

    public function testTransformLongDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::LONG);

        $this->assertEquals('03. Februar 2010 04:05', $transformer->transform($this->dateTime));
    }

    public function testTransformFullDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL);

        $this->assertEquals('Mittwoch, 03. Februar 2010 04:05', $transformer->transform($this->dateTime));
    }

    public function testTransformShortTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::SHORT);

        $this->assertEquals('03.02.2010 04:05', $transformer->transform($this->dateTime));
    }

    public function testTransformMediumTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::MEDIUM);

        $this->assertEquals('03.02.2010 04:05:06', $transformer->transform($this->dateTime));
    }

    public function testTransformLongTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::LONG);

        $this->assertEquals('03.02.2010 04:05:06 GMT+00:00', $transformer->transform($this->dateTime));
    }

    public function testTransformFullTime()
    {
        if ($this->isLowerThanIcuVersion(3.8)) {
            $this->markTestSkipped('Please upgrade ICU version to 3.8+');
        }

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::FULL);

        $this->assertEquals('03.02.2010 04:05:06 GMT+00:00', $transformer->transform($this->dateTime));
    }

    public function testTransformToDifferentLocale()
    {
        \Locale::setDefault('en_US');

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');

        $this->assertEquals('Feb 3, 2010 4:05 AM', $transformer->transform($this->dateTime));
    }

    public function testTransform_empty()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testTransform_differentTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = clone $input;
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($dateTime->format('d.m.Y H:i'), $transformer->transform($input));
    }

    public function testTransform_differentPatterns()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'MM*yyyy*dd HH|mm|ss');

        $this->assertEquals('02*2010*03 04|05|06', $transformer->transform($this->dateTime));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformRequiresValidDateTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->transform('2010-01-01');
    }

    public function testTransformWrapsIntlErrors()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        // HOW TO REPRODUCE?

        //$this->setExpectedException('Symfony\Component\Form\Extension\Core\DataTransformer\Transdate_formationFailedException');

        //$transformer->transform(1.5);
    }

    public function testReverseTransformShortDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::SHORT);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('03.02.10 04:05'));
    }

    public function testReverseTransformMediumDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::MEDIUM);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('03.02.2010 04:05'));
    }

    public function testReverseTransformLongDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::LONG);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('03. Februar 2010 04:05'));
    }

    public function testReverseTransformFullDate()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('Mittwoch, 03. Februar 2010 04:05'));
    }

    public function testReverseTransformShortTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::SHORT);

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('03.02.2010 04:05'));
    }

    public function testReverseTransformMediumTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::MEDIUM);

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('03.02.2010 04:05:06'));
    }

    public function testReverseTransformLongTime()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::LONG);

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('03.02.2010 04:05:06 GMT+00:00'));
    }

    public function testReverseTransformFullTime()
    {
        if ($this->isLowerThanIcuVersion(3.8)) {
            $this->markTestSkipped('Please upgrade ICU version to 3.8+');
        }

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', null, \IntlDateFormatter::FULL);

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('03.02.2010 04:05:06 GMT+00:00'));
    }

    public function testReverseTransformFromDifferentLocale()
    {
        \Locale::setDefault('en_US');

        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC');

        $this->assertDateTimeEquals($this->dateTimeWithoutSeconds, $transformer->reverseTransform('Feb 3, 2010 04:05 AM'));
    }

    public function testReverseTransform_differentTimezones()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('America/New_York', 'Asia/Hong_Kong');

        $dateTime = new \DateTime('2010-02-03 04:05:00 Asia/Hong_Kong');
        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($dateTime, $transformer->reverseTransform('03.02.2010 04:05'));
    }

    public function testReverseTransform_differentPatterns()
    {
        $transformer = new DateTimeToLocalizedStringTransformer('UTC', 'UTC', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, \IntlDateFormatter::GREGORIAN, 'MM*yyyy*dd HH|mm|ss');

        $this->assertDateTimeEquals($this->dateTime, $transformer->reverseTransform('02*2010*03 04|05|06'));
    }

    public function testReverseTransform_empty()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformRequiresString()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform(12345);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformWrapsIntlErrors()
    {
        $transformer = new DateTimeToLocalizedStringTransformer();
        $transformer->reverseTransform('12345');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testValidateDateFormatOption()
    {
        new DateTimeToLocalizedStringTransformer(null, null, 'foobar');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testValidateTimeFormatOption()
    {
        new DateTimeToLocalizedStringTransformer(null, null, null, 'foobar');
    }
}
