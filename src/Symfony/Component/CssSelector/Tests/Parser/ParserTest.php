<?php

namespace Symfony\Component\CssSelector\Tests\Parser;

use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getParserTestData */
    public function testParser($source, $representation)
    {
        $parser = new Parser();

        $this->assertEquals($representation, array_map(function (SelectorNode $node) {
            return (string) $node->getTree();
        }, $parser->parse($source)));
    }

    public function getParserTestData()
    {
        return array(
            array('*', array('Element[*]')),
//            array('*|*', array('Element[*]')),
//            array('*|foo', array('Element[foo]')),
//            array('foo|*', array('Element[foo|*]')),
//            array('foo|bar', array('Element[foo|bar]')),
//            array('#foo#bar', array('Hash[Hash[Element[*]#foo]#bar]')),
//            array('div>.foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array('div> .foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array('div >.foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array('div > .foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array("div \n>  \t \t .foo', array('div\r>\n\n\n.foo'), array('div\f>\f.foo", ')CombinedSelector[Element[div] > Class[Element[*].foo]]'),
//            array('td.foo,.bar', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array('td.foo, .bar', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array("td.foo\t\r\n\f ,\t\r\n\f .bar", array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
//            array('td.foo,.bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
//            array('td.foo, .bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
//            array("td.foo\t\r\n\f ,\t\r\n\f .bar", array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
        );
    }
}
