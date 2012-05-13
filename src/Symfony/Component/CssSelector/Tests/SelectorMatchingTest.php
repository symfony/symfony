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

use Symfony\Component\CssSelector\CssSelector;

class SelectorMatchingTest extends \PHPUnit_Framework_TestCase
{
    private static $_XML = <<<EOS
<root>

  <ul>
    <li class="first">1</li>
    <li>2</li>
    <li>3</li>
    <li>4</li>
    <li>5</li>
    <li>6</li>
    <li>7</li>
    <li>8</li>
    <li>9</li>
    <li>10</li>
    <li>11</li>
    <li class="last">12</li>
  </ul>

  <p id="p-1" class="first">P-1</p>
  <p id="p-2">P-2</p>
  <p id="p-3">P-3</p>
  <p id="p-4">P-4</p>
  <p id="p-5" class="last">P-5</p>

  <b foo="à">B-1</b>
  <b foo="é">B-2</b>
  <b foo="î">B-3</b>
  <b foo="ö">B-4</b>
  <b foo="é-ö">B-5</b>
  <b foo="àé">B-6</b>
  <b foo="àé öù">B-7</b>
  <b foo="où îö ùÿ">B-8</b>

</root>
EOS;

    private static
        $_DOM = null,
        $_XPATH = null;

    public static function setUpBeforeClass()
    {
        self::$_DOM = \DOMDocument::loadXML(self::$_XML);
        self::$_XPATH = new \DOMXPath(self::$_DOM);
    }

    /**
     * @dataProvider testNthyNessProvider
     **/
    public function testNthyNess($input, $expected)
    {
        $nodeset = self::$_XPATH->query(CssSelector::toXpath($input));
        $results = array();
        foreach ($nodeset as $node) {
            $results[] = intval(trim($node->textContent));
        }
        $this->assertEquals($expected, $results); 
    }
    public function testNthyNessProvider()
    {
        return array(
            array(
              'ul>li:first-child', array(1)
            ),
            array(
              'ul>li:first-of-type', array(1)
            ),
            array(
              'ul>li:last-child', array(12)
            ),
            array(
              'ul>li:last-of-type', array(12)
            ),
            array(
              'ul>li:nth-child()', array()
            ),
            array(
              'ul>li:nth-child(3)', array(3)
            ),
            array(
              'ul>li:nth-child(odd)', array(1,3,5,7,9,11)
            ),
            array(
              'ul>li:nth-child(2n+1)', array(1,3,5,7,9,11)
            ),
            array(
              'ul>li:nth-child(even)', array(2,4,6,8,10,12)
            ),
            array(
              'ul>li:nth-child(2n)', array(2,4,6,8,10,12)
            ),
            array(
              'ul>li:nth-child(4n+3)', array(3,7,11)
            ),
            array(
              'ul>li:nth-child(3n+4)', array(4,7,10)
            ),
            array(
              'ul>li:nth-child(-n+3)', array(1,2,3)
            ),
            array(
              'ul>li:nth-child(n+3)', array(3,4,5,6,7,8,9,10,11,12)
            ),
            array(
              'ul>li:nth-last-child()', array()
            ),
            array(
              'ul>li:nth-last-child(1)', array(12)
            ),
            array(
              'ul>li:nth-last-child(3)', array(10)
            ),
            array(
              'ul>li:nth-last-child(-3)', array()
            ),
            array(
              'ul>li:nth-last-child(n+3)', array(1,2,3,4,5,6,7,8,9,10)
            ),
            array(
              'ul>li:nth-last-child(-n+3)', array(10,11,12)
            ),

        );
    }

    /**
     * @dataProvider testSiblingsProvider
     **/
    public function testSiblings($input, $expected)
    {
        $nodeset = self::$_XPATH->query(CssSelector::toXpath($input));
        $results = array();
        foreach ($nodeset as $node) {
            $results[] = trim($node->textContent);
        }
        $this->assertEquals($expected, $results);
    }
    public function testSiblingsProvider()
    {
        return array(
            array('#p-3 + p', array('P-4')),
            array('#p-5 + p', array()),
            array('#p-3 ~ p', array('P-4', 'P-5')),
            array('#p-5 ~ p', array()),
        );
    }


    /**
     * @dataProvider testAttributesProvider
     **/
    public function testAttributes($input, $expected)
    {
        $nodeset = self::$_XPATH->query(CssSelector::toXpath($input));
        $results = array();
        foreach ($nodeset as $node) {
            $results[] = trim($node->textContent);
        }
        $this->assertEquals($expected, $results);
    }
    public function testAttributesProvider()
    {
        return array(
            array('b[foo="à"]', array('B-1')),
            array('b[foo^="à"]', array('B-1','B-6','B-7')),
            array('b[foo^="ö"]', array('B-4')),
            array('b[foo$="à"]', array('B-1')),
            array('b[foo$="öù"]', array('B-7')),
            array('b[foo*="àé"]', array('B-6','B-7')),
            array('b[foo*="é"]', array('B-2','B-5','B-6','B-7')),
            array('b[foo|="é"]', array('B-2','B-5')),
            array('b[foo~="îö"]', array('B-8')),
        );
    }

    /**
     * @depends testNthyNess
     * @dataProvider testNegationProvider
     **/
    public function testNegation($input, $expected)
    {
        $nodeset = self::$_XPATH->query(CssSelector::toXpath($input));
        $results = array();
        foreach ($nodeset as $node) {
            $results[] = trim($node->textContent);
        }
        $this->assertEquals($expected, $results);
    }
    public function testNegationProvider()
    {
        return array(
            array('p:not(.first):not(.last)', array('P-2','P-3','P-4')),
            array('p:not(:first-child):not(:last-child)', array('P-2','P-3','P-4')),
            array('p:not(:nth-child(odd))', array('P-2','P-4')),
            array('p:not(:nth-child(even))', array('P-1','P-3', 'P-5')),
            array('.last:not(li)', array('P-5')),
            // FIXME: This should be parsed correctly
            //array('.last:not(*|li)', array('P-5')),
        );
    }
}
