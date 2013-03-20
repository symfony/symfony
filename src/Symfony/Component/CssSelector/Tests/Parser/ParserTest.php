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
            array('*|*', array('Element[*]')),
            array('*|foo', array('Element[foo]')),
            array('foo|*', array('Element[foo|*]')),
            array('foo|bar', array('Element[foo|bar]')),
            array('#foo#bar', array('Hash[Hash[Element[*]#foo]#bar]')),
            array('div>.foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
            array('div> .foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
            array('div >.foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
            array('div > .foo', array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
            array("div \n>  \t \t .foo", array('CombinedSelector[Element[div] > Class[Element[*].foo]]')),
            array('td.foo,.bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array('td.foo, .bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array("td.foo\t\r\n\f ,\t\r\n\f .bar", array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array('td.foo,.bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array('td.foo, .bar', array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array("td.foo\t\r\n\f ,\t\r\n\f .bar", array('Class[Element[td].foo]', 'Class[Element[*].bar]')),
            array('div, td.foo, div.bar span', array('Element[div]', 'Class[Element[td].foo]', 'CombinedSelector[Class[Element[div].bar] <followed> Element[span]]')),
            array('div > p', array('CombinedSelector[Element[div] > Element[p]]')),
            array('td:first', array('Pseudo[Element[td]:first]')),
            array('td :first', array('CombinedSelector[Element[td] <followed> Pseudo[Element[*]:first]]')),
            array('a[name]', array('Attribute[Element[a][name]]')),
            array("a[ name\t]", array('Attribute[Element[a][name]]')),
            array('a [name]', array('CombinedSelector[Element[a] <followed> Attribute[Element[*][name]]]')),
            array('a[rel="include"]', array("Attribute[Element[a][rel = 'include']]")),
            array('a[rel = include]', array("Attribute[Element[a][rel = 'include']]")),
            array("a[hreflang |= 'en']", array("Attribute[Element[a][hreflang |= 'en']]")),
            array('a[hreflang|=en]', array("Attribute[Element[a][hreflang |= 'en']]")),
            array('div:nth-child(10)', array("Function[Element[div]:nth-child(['10'])]")),
            array(':nth-child(2n+2)', array("Function[Element[*]:nth-child(['2', 'n', '+2'])]")),
            array('div:nth-of-type(10)', array("Function[Element[div]:nth-of-type(['10'])]")),
            array('div div:nth-of-type(10) .aclass', array("CombinedSelector[CombinedSelector[Element[div] <followed> Function[Element[div]:nth-of-type(['10'])]] <followed> Class[Element[*].aclass]]")),
            array('label:only', array('Pseudo[Element[label]:only]')),
            array('a:lang(fr)', array("Function[Element[a]:lang(['fr'])]")),
            array('div:contains("foo")', array("Function[Element[div]:contains(['foo'])]")),
            array('div#foobar', array('Hash[Element[div]#foobar]')),
            array('div:not(div.foo)', array('Negation[Element[div]:not(Class[Element[div].foo])]')),
            array('td ~ th', array('CombinedSelector[Element[td] ~ Element[th]]')),
        );
    }
}
