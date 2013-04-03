<?php

namespace Symfony\Component\Console\Tests\Descriptor\Json\Document;

use Symfony\Component\Console\Descriptor\Markdown\Document\Formatter;

class FormatterTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getClipTestData */
    public function testClip($content, $maxLength, $expectedLines)
    {
        $formatter = new Formatter($maxLength);
        $this->assertEquals($expectedLines, $formatter->clip($content));
    }

    public function getClipTestData()
    {
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit.';

        return array(
            array('a b c d e', 3, array('a b', 'c d', 'e')),
            array($lorem, 20, array('Lorem ipsum dolor', 'sit amet,', 'consectetur', 'adipisicing elit.')),
            array($lorem, 30, array('Lorem ipsum dolor sit amet,', 'consectetur adipisicing elit.')),
            array($lorem, 60, array($lorem)),
        );
    }
}