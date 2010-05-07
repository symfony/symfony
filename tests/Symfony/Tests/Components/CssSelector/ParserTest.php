<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\CssSelector;

use Symfony\Components\CssSelector\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testCssToXpath()
    {
        $this->assertEquals('descendant-or-self::h1', Parser::cssToXpath('h1'));
        $this->assertEquals("descendant-or-self::h1[@id = 'foo']", Parser::cssToXpath('h1#foo'));
        $this->assertEquals("descendant-or-self::h1[contains(concat(' ', normalize-space(@class), ' '), ' foo ')]", Parser::cssToXpath('h1.foo'));

        $this->assertEquals('descendant-or-self::foo:h1', Parser::cssToXpath('foo|h1'));
    }

    /**
     * @dataProvider getCssSelectors
     */
    public function testParse($css, $xpath)
    {
        $parser = new Parser();

        $this->assertEquals($xpath, (string) $parser->parse($css)->toXpath(), '->parse() parses an input string and returns a node');
    }

    public function testParseExceptions()
    {
        $parser = new Parser();

        try {
            $parser->parse('h1:');
            $this->fail('->parse() throws an Exception if the css selector is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Symfony\Components\CssSelector\SyntaxError', $e, '->parse() throws an Exception if the css selector is not valid');
            $this->assertEquals("Expected symbol, got '' at h1: -> ", $e->getMessage(), '->parse() throws an Exception if the css selector is not valid');
        }
    }

    public function getCssSelectors()
    {
        return array(
            array('h1', "h1"),
            array('foo|h1', "foo:h1"),
            array('h1, h2, h3', "h1 | h2 | h3"),
            array('h1:nth-child(3n+1)', "*/*[name() = 'h1' and ((position() -1) mod 3 = 0 and position() >= 1)]"),
            array('h1 > p', "h1/p"),
            array('h1#foo', "h1[@id = 'foo']"),
            array('h1.foo', "h1[contains(concat(' ', normalize-space(@class), ' '), ' foo ')]"),
            array('h1[class*="foo bar"]', "h1[contains(@class, 'foo bar')]"),
            array('h1[foo|class*="foo bar"]', "h1[contains(@foo:class, 'foo bar')]"),
            array('h1[class]', "h1[@class]"),
            array('h1 .foo', "h1/descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' foo ')]"),
            array('h1 #foo', "h1/descendant::*[@id = 'foo']"),
            array('h1 [class*=foo]', "h1/descendant::*[contains(@class, 'foo')]"),
        );
    }
}
