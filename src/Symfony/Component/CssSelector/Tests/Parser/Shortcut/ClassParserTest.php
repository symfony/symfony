<?php

namespace Symfony\Component\CssSelector\Tests\Parser\Shortcut;

use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\Shortcut\ClassParser;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ClassParserTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getParseTestData */
    public function testParse($source, $representation)
    {
        $parser = new ClassParser();
        $selectors = $parser->parse($source);
        $this->assertEquals(1, count($selectors));

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals($representation, (string) $selector->getTree());
    }

    public function getParseTestData()
    {
        return array(
            array('.class', 'Class[Element[*].class]'),
        );
    }
}
