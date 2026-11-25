<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\InlineFormatting;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class BlockInInlineTest extends TestCase
{
    public function testBlockDescendantSplitsAnInlineWithoutChangingItsSemanticParent()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0;font-size:16px;line-height:16px}div{border:1px solid}</style><main>Before<span>start<div>block</div>end</span>after</main>', new RenderingContext(160, 160));
        $document = $result->getDocument();
        $span = $document->querySelector('span');
        $block = $document->querySelector('div');
        self::assertSame($span, $block->parentElement);
        self::assertSame(160.0, $result->getBounds($block)[0]->width);
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['Before', 'start', 'block', 'end', 'after'], array_column($text, 'text'));
        self::assertSame([0.0, 0.0, 17.0, 34.0, 34.0], array_column($text, 'y'));
        self::assertSame([0.0, 48.0, 1.0, 0.0, 24.0], array_column($text, 'x'));
        self::assertCount(2, $result->getBounds($span));
    }

    public function testNestedInlineSplitsKeepInheritedStylesAndIdentity()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0;font-size:16px;line-height:16px}span{color:red}</style><main>A<span>B<i>C<div>D</div>E</i>F</span>G</main>', new RenderingContext(160, 160));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G'], array_column($text, 'text'));
        self::assertSame([0.0, 0.0, 0.0, 16.0, 32.0, 32.0, 32.0], array_column($text, 'y'));
        self::assertSame('red', $text[3]['color']);
        self::assertSame('red', $text[4]['color']);
        self::assertCount(2, $result->getBounds($result->getDocument()->querySelector('i')));
    }

    public function testAtomicInlineAndOutOfFlowDescendantsAreNotLifted()
    {
        $budget = 10;
        $block = new Box(null, 'block');
        self::assertNull(InlineFormatting::splitBlocks(new Box(null, 'inline-block', [], [$block]), $budget));
        self::assertNull(InlineFormatting::splitBlocks(new Box(null, 'inline', [], [new Box(null, 'block', ['position' => 'absolute']), new Box(null, 'block', ['float' => 'left'])]), $budget));
        self::assertSame(10, $budget);
    }

    public function testGeneratedInlineFragmentsHaveABoundedAllocationBudget()
    {
        $budget = 1;
        $box = new Box(null, 'inline', [], [new Box(null, 'inline', [], [], 'before'), new Box(null, 'block'), new Box(null, 'inline', [], [], 'after')]);
        $this->expectException(\LengthException::class);
        InlineFormatting::splitBlocks($box, $budget);
    }
}
