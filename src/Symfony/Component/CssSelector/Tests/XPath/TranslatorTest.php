<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\XPath;

use Symfony\Component\CssSelector\XPath\Translator;

class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getXpathLiteralTestData */
    public function testXpathLiteral($value, $literal)
    {
        $this->assertEquals($literal, Translator::getXpathLiteral($value));
    }

    /** @dataProvider getCssToXPathTestData */
    public function testCssToXPath($css, $xpath)
    {
        $translator = new Translator();

        $this->assertEquals($xpath, $translator->cssToXPath($css, ''));
    }

    public function getXpathLiteralTestData()
    {
        return array(
            array('foo', "'foo'"),
            array("foo's bar", '"foo\'s bar"'),
            array("foo's \"middle\" bar", 'concat(\'foo\', "\'", \'s "middle" bar\')'),
            array("foo's 'middle' \"bar\"", 'concat(\'foo\', "\'", \'s \', "\'", \'middle\', "\'", \' "bar"\')'),
        );
    }

    public function getCssToXPathTestData()
    {
        return array(
            array('*', "*"),
            array('e', "e"),
            array('*|e', "e"),
            array('e|f', "e:f"),
            array('e[foo]', "e[@foo]"),
            array('e[foo|bar]', "e[@foo:bar]"),
            array('e[foo="bar"]', "e[@foo = 'bar']"),
            array('e[foo~="bar"]', "e[@foo and contains(concat(' ', normalize-space(@foo), ' '), ' bar ')]"),
            array('e[foo^="bar"]', "e[@foo and starts-with(@foo, 'bar')]"),
            array('e[foo$="bar"]', "e[@foo and substring(@foo, string-length(@foo)-2) = 'bar']"),
            array('e[foo*="bar"]', "e[@foo and contains(@foo, 'bar')]"),
            array('e[hreflang|="en"]', "e[@hreflang and (@hreflang = 'en' or starts-with(@hreflang, 'en-'))]"),
            array('e:nth-child(1)', "*/*[name() = 'e' and (position() = 1)]"),
            array('e:nth-last-child(1)', "*/*[name() = 'e' and (position() = last() - 1)]"),
            array('e:nth-last-child(2n+2)', "*/*[name() = 'e' and ((position() +2) mod -2 = 0 and position() < (last() -2))]"),
            array('e:nth-of-type(1)', "*/e[position() = 1]"),
            array('e:nth-last-of-type(1)', "*/e[position() = last() - 1]"),
            array('e:nth-last-of-type(1)', "*/e[position() = last() - 1]"),
            array('div e:nth-last-of-type(1) .aclass', "div/descendant-or-self::*/e[position() = last() - 1]/descendant-or-self::*/*[@class and contains(concat(' ', normalize-space(@class), ' '), ' aclass ')]"),
            array('e:first-child', "*/*[name() = 'e' and (position() = 1)]"),
            array('e:last-child', "*/*[name() = 'e' and (position() = last())]"),
            array('e:first-of-type', "*/e[position() = 1]"),
            array('e:last-of-type', "*/e[position() = last()]"),
            array('e:only-child', "*/*[name() = 'e' and (last() = 1)]"),
            array('e:only-of-type', "e[last() = 1]"),
            array('e:empty', "e[not(*) and not(string-length())]"),
            array('e:EmPTY', "e[not(*) and not(string-length())]"),
            array('e:root', "e[not(parent::*)]"),
            array('e:hover', "e[0]"),
            array('e:contains("foo")', "e[contains(string(.), 'foo')]"),
            array('e:ConTains(foo)', "e[contains(string(.), 'foo')]"),
            array('e.warning', "e[@class and contains(concat(' ', normalize-space(@class), ' '), ' warning ')]"),
            array('e#myid', "e[@id = 'myid']"),
            array('e:not(:nth-child(odd))', "e[not((position() -1) mod 2 = 0 and position() >= 1)]"),
            array('e:nOT(*)', "e[0]"),
            array('e f', "e/descendant-or-self::*/f"),
            array('e > f', "e/f"),
            array('e + f', "e/following-sibling::*[name() = 'f' and (position() = 1)]"),
            array('e ~ f', "e/following-sibling::f"),
            array('div#container p', "div[@id = 'container']/descendant-or-self::*/p"),

            array('di\a0 v', "*[name() = 'di v']"),
            array('di\[v', "*[name() = 'di[v']"),
            array('[h\a0 ref]', "*[attribute::*[name() = 'h ref']]"),
            array('[h\]ref]', "*[attribute::*[name() = 'h]ref']]"),
        );
    }
}
