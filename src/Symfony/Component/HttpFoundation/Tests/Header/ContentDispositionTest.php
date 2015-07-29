<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Header;

use Symfony\Component\HttpFoundation\Header\ContentDisposition;

class ContentDispositionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidDisposition()
    {
        new ContentDisposition('invalid', 'foo.html');
    }

    /**
     * @dataProvider provideMakeDisposition
     */
    public function testMakeDisposition($disposition, $filename, $filenameFallback, $expected)
    {
        $this->assertEquals($expected, new ContentDisposition($disposition, $filename, $filenameFallback));
    }

    public function provideMakeDisposition()
    {
        return array(
            array('attachment', 'foo.html', 'foo.html', 'attachment; filename="foo.html"'),
            array('attachment', 'foo.html', '', 'attachment; filename="foo.html"'),
            array('attachment', 'foo bar.html', '', 'attachment; filename="foo bar.html"'),
            array('attachment', 'foo "bar".html', '', 'attachment; filename="foo \\"bar\\".html"'),
            array('attachment', 'foo%20bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"; filename*=utf-8\'\'foo%2520bar.html'),
            array('attachment', 'föö.html', 'foo.html', 'attachment; filename="foo.html"; filename*=utf-8\'\'f%C3%B6%C3%B6.html'),
        );
    }

    /**
     * @dataProvider provideFromString
     */
    public function testFromString($disposition, $filename, $filenameFallback, $header)
    {
        $contentDisposition = ContentDisposition::fromString($header);
        $this->assertEquals($header, (string)$contentDisposition);
        $this->assertEquals($disposition, $contentDisposition->getDisposition());
        $this->assertEquals($filename, $contentDisposition->getFilename());
        $this->assertEquals($filenameFallback, $contentDisposition->getFilenameFallback());
    }

    public function provideFromString()
    {
        return array(
            array('attachment', 'foo.html', 'foo.html', 'attachment; filename="foo.html"'),
            array('attachment', 'foo.html', 'foo.html', 'attachment; filename="foo.html"'),
            array('attachment', 'foo bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"'),
            array('attachment', 'foo "bar".html', 'foo "bar".html', 'attachment; filename="foo \\"bar\\".html"'),
            array('attachment', 'foo%20bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"; filename*=utf-8\'\'foo%2520bar.html'),
            array('attachment', 'föö.html', 'foo.html', 'attachment; filename="foo.html"; filename*=utf-8\'\'f%C3%B6%C3%B6.html'),
        );
    }
}
