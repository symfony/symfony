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

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\Parser\NativeParser;

#[RequiresPhp('>=8.4')]
class NativeParserTest extends TestCase
{
    public function testParseValid()
    {
        $node = (new NativeParser())->parse('<div></div>');
        $this->assertInstanceOf(\Dom\Node::class, $node);
        $this->assertSame('BODY', $node->nodeName);
        $this->assertCount(1, $node->childNodes);
        $this->assertSame('DIV', $node->childNodes->item(0)->nodeName);
    }

    public function testParseHtml()
    {
        $html = '<div><p>Hello <strong>World</strong>!</p></div>';
        $node = (new NativeParser())->parse($html);
        $this->assertInstanceOf(\Dom\Node::class, $node);
        $this->assertSame('BODY', $node->nodeName);
        $this->assertCount(1, $node->childNodes);
        $this->assertSame('DIV', $node->childNodes->item(0)->nodeName);
    }
}
