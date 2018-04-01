<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\CssSelector\Tests\Parser\Shortcut;

use PHPUnit\Framework\TestCase;
use Symphony\Component\CssSelector\Node\SelectorNode;
use Symphony\Component\CssSelector\Parser\Shortcut\HashParser;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class HashParserTest extends TestCase
{
    /** @dataProvider getParseTestData */
    public function testParse($source, $representation)
    {
        $parser = new HashParser();
        $selectors = $parser->parse($source);
        $this->assertCount(1, $selectors);

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals($representation, (string) $selector->getTree());
    }

    public function getParseTestData()
    {
        return array(
            array('#testid', 'Hash[Element[*]#testid]'),
            array('testel#testid', 'Hash[Element[testel]#testid]'),
            array('testns|#testid', 'Hash[Element[testns|*]#testid]'),
            array('testns|*#testid', 'Hash[Element[testns|*]#testid]'),
            array('testns|testel#testid', 'Hash[Element[testns|testel]#testid]'),
        );
    }
}
