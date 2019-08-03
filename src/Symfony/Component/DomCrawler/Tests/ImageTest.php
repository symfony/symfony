<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Image;

class ImageTest extends TestCase
{
    public function testConstructorWithANonImgTag()
    {
        $this->expectException('LogicException');
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><div><div></html>');

        new Image($dom->getElementsByTagName('div')->item(0), 'http://www.example.com/');
    }

    public function testBaseUriIsOptionalWhenImageUrlIsAbsolute()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><img alt="foo" src="https://example.com/foo" /></html>');

        $image = new Image($dom->getElementsByTagName('img')->item(0));
        $this->assertSame('https://example.com/foo', $image->getUri());
    }

    public function testAbsoluteBaseUriIsMandatoryWhenImageUrlIsRelative()
    {
        $this->expectException('InvalidArgumentException');
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><img alt="foo" src="/foo" /></html>');

        $image = new Image($dom->getElementsByTagName('img')->item(0), 'example.com');
        $image->getUri();
    }

    /**
     * @dataProvider getGetUriTests
     */
    public function testGetUri($url, $currentUri, $expected)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML(sprintf('<html><img alt="foo" src="%s" /></html>', $url));
        $image = new Image($dom->getElementsByTagName('img')->item(0), $currentUri);

        $this->assertEquals($expected, $image->getUri());
    }

    public function getGetUriTests()
    {
        return [
            ['/foo.png', 'http://localhost/bar/foo/', 'http://localhost/foo.png'],
            ['foo.png', 'http://localhost/bar/foo/', 'http://localhost/bar/foo/foo.png'],
        ];
    }
}
