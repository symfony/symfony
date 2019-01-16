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
    /**
     * @expectedException \LogicException
     */
    public function testConstructorWithANonImgTag()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><div><div></html>');

        new Image($dom->getElementsByTagName('div')->item(0), 'http://www.example.com/');
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
