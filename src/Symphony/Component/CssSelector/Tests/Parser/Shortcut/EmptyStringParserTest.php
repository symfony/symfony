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
use Symphony\Component\CssSelector\Parser\Shortcut\EmptyStringParser;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class EmptyStringParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new EmptyStringParser();
        $selectors = $parser->parse('');
        $this->assertCount(1, $selectors);

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals('Element[*]', (string) $selector->getTree());

        $selectors = $parser->parse('this will produce an empty array');
        $this->assertCount(0, $selectors);
    }
}
