<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\Parser\MastermindsParser;

class MastermindsParserTest extends TestCase
{
    public function testParseValid()
    {
        $node = (new MastermindsParser())->parse('<div></div>');
        $this->assertInstanceOf(\DOMNode::class, $node);
        $this->assertSame('#document-fragment', $node->nodeName);
        $this->assertCount(1, $node->childNodes);
        $this->assertSame('div', $node->childNodes->item(0)->nodeName);
    }
}
