<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests;

use Symfony\Component\CssSelector\CssSelectorConverter;

class CssSelectorConverterTest extends \PHPUnit_Framework_TestCase
{
    public function testCssToXPath()
    {
        $converter = new CssSelectorConverter();

        $this->assertEquals('descendant-or-self::*', $converter->toXPath(''));
        $this->assertEquals('descendant-or-self::h1', $converter->toXPath('h1'));
        $this->assertEquals("descendant-or-self::h1[@id = 'foo']", $converter->toXPath('h1#foo'));
        $this->assertEquals("descendant-or-self::h1[@class and contains(concat(' ', normalize-space(@class), ' '), ' foo ')]", $converter->toXPath('h1.foo'));
        $this->assertEquals('descendant-or-self::foo:h1', $converter->toXPath('foo|h1'));
        $this->assertEquals('descendant-or-self::h1', $converter->toXPath('H1'));
    }

    public function testCssToXPathXml()
    {
        $converter = new CssSelectorConverter(false);

        $this->assertEquals('descendant-or-self::H1', $converter->toXPath('H1'));
    }
}
