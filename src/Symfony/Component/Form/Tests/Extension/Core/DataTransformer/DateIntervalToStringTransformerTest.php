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
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateIntervalToStringTransformer;

/**
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
class DateIntervalToStringTransformerTest extends DateIntervalTestCase
{
    public function dataProviderISO()
    {
        $data = [
            ['P%YY%MM%DDT%HH%IM%SS', 'P00Y00M00DT00H00M00S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P0Y0M0DT0H0M0S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P10Y2M3DT16H5M6S', 'P10Y2M3DT16H5M6S'],
            ['P%yY%mM%dDT%hH%iM', 'P10Y2M3DT16H5M', 'P10Y2M3DT16H5M'],
            ['P%yY%mM%dDT%hH', 'P10Y2M3DT16H', 'P10Y2M3DT16H'],
            ['P%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'],
        ];

        return $data;
    }

    public function dataProviderDate()
    {
        $data = [
            [
                '%y years %m months %d days %h hours %i minutes %s seconds',
                '10 years 2 months 3 days 16 hours 5 minutes 6 seconds',
                'P10Y2M3DT16H5M6S',
            ],
            [
                '%y years %m months %d days %h hours %i minutes',
                '10 years 2 months 3 days 16 hours 5 minutes',
                'P10Y2M3DT16H5M',
            ],
            ['%y years %m months %d days %h hours', '10 years 2 months 3 days 16 hours', 'P10Y2M3DT16H'],
            ['%y years %m months %d days', '10 years 2 months 3 days', 'P10Y2M3D'],
            ['%y years %m months', '10 years 2 months', 'P10Y2M'],
            ['%y year', '1 year', 'P1Y'],
        ];

        return $data;
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testTransform($format, $output, $input)
    {
        $transformer = new DateIntervalToStringTransformer($format);
        $input = new \DateInterval($input);
        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new DateIntervalToStringTransformer();
        $this->assertSame('', $transformer->transform(null));
    }

    public function testTransformExpectsDateTime()
    {
        $transformer = new DateIntervalToStringTransformer();
        $this->expectException(UnexpectedTypeException::class);
        $transformer->transform('1234');
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testReverseTransform($format, $input, $output)
    {
        $reverseTransformer = new DateIntervalToStringTransformer($format, true);
        $interval = new \DateInterval($output);
        $this->assertDateIntervalEquals($interval, $reverseTransformer->reverseTransform($input));
    }

    /**
     * @dataProvider dataProviderDate
     */
    public function testReverseTransformDateString($format, $input, $output)
    {
        $reverseTransformer = new DateIntervalToStringTransformer($format, true);
        $interval = new \DateInterval($output);
        $this->expectException(TransformationFailedException::class);
        $this->assertDateIntervalEquals($interval, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $reverseTransformer = new DateIntervalToStringTransformer();
        $this->assertNull($reverseTransformer->reverseTransform(''));
    }

    public function testReverseTransformExpectsString()
    {
        $reverseTransformer = new DateIntervalToStringTransformer();
        $this->expectException(UnexpectedTypeException::class);
        $reverseTransformer->reverseTransform(1234);
    }

    public function testReverseTransformExpectsValidIntervalString()
    {
        $reverseTransformer = new DateIntervalToStringTransformer();
        $this->expectException(TransformationFailedException::class);
        $reverseTransformer->reverseTransform('10Y');
    }
}
