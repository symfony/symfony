<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\NativeCrawler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\NativeCrawler\Image;

/**
 * @requires PHP 8.4
 */
class ImageTest extends TestCase
{
    public function testConstructorWithANonImgTagFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><div><div></html>');

        $this->expectException(\LogicException::class);
        new Image($dom->getElementsByTagName('div')->item(0), 'http://www.example.com/');
    }

    public function testBaseUriIsOptionalWhenImageUrlIsAbsoluteFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><img alt="foo" src="https://example.com/foo"></html>');

        $image = new Image($dom->getElementsByTagName('img')->item(0));
        $this->assertSame('https://example.com/foo', $image->getUri());
    }

    public function testAbsoluteBaseUriIsMandatoryWhenImageUrlIsRelativeFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><img alt="foo" src="/foo"></html>');

        $this->expectException(\InvalidArgumentException::class);
        new Image($dom->getElementsByTagName('img')->item(0), 'example.com');
    }

    /**
     * @dataProvider getGetUriTests
     */
    public function testGetUriFromHTMLDocument($url, $currentUri, $expected)
    {
        $dom = \DOM\HTMLDocument::createFromString(sprintf('<!DOCTYPE html><html><img alt="foo" src="%s"></html>', $url));

        $image = new Image($dom->getElementsByTagName('img')->item(0), $currentUri);

        $this->assertEquals($expected, $image->getUri());
    }

    public static function getGetUriTests()
    {
        return [
            ['/foo.png', 'http://localhost/bar/foo/', 'http://localhost/foo.png'],
            ['foo.png', 'http://localhost/bar/foo/', 'http://localhost/bar/foo/foo.png'],
        ];
    }
}
