<?php

namespace Symfony\Tests\Components\Validator\MessageInterpolator;

use Symfony\Components\Validator\MessageInterpolator\XliffMessageInterpolator;

class XliffMessageInterpolatorTest extends \PHPUnit_Framework_TestCase
{
    public function testInterpolateText()
    {
        $interpolator = new XliffMessageInterpolator(__DIR__.'/xliff.xml');
        $text = $interpolator->interpolate('original', array('param' => 'foobar'));

        $this->assertEquals('translation with param foobar', $text);
    }

    public function testInterpolateFromMultipleFiles()
    {
        $interpolator = new XliffMessageInterpolator(array(
            __DIR__.'/xliff.xml',
            __DIR__.'/xliff2.xml',
        ));

        $text1 = $interpolator->interpolate('original', array('param' => 'foobar'));
        $text2 = $interpolator->interpolate('second', array('param' => 'baz'));

        $this->assertEquals('translation with param foobar', $text1);
        $this->assertEquals('second translation with param baz', $text2);
    }

    public function testConvertParamsToStrings()
    {
        $interpolator = new XliffMessageInterpolator(__DIR__.'/xliff.xml');
        $text = $interpolator->interpolate('original', array('param' => array()));

        $this->assertEquals('translation with param Array', $text);
    }
}

