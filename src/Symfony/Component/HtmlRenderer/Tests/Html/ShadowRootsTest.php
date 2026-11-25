<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Html\ShadowRoots;

final class ShadowRootsTest extends TestCase
{
    public function testCompositionKeepsAssignedNativeNodesAndFallbackSlots()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><x-card><template shadowrootmode="open"><h2><slot name="title">Fallback title</slot></h2><slot>Fallback body</slot><slot name="empty">Fallback empty</slot></template><b id="title" slot="title">Title</b><p>Body</p></x-card>');
        $title = $document->querySelector('#title');
        self::assertSame([1 => 1], ShadowRoots::compose($document));
        self::assertSame($title, $document->querySelector('h2 b'));
        self::assertSame('TitleBodyFallback empty', $document->querySelector('x-card')->textContent);
        self::assertSame('1', $title->getAttribute(Selector::SLOTTED_ATTRIBUTE));
        self::assertSame('1', $document->querySelector('h2')->getAttribute(Selector::TREE_ATTRIBUTE));
    }

    public function testNestedPartsAreExportedOnlyByTheirDeclaredNames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><x-outer><template shadowrootmode="open"><x-inner exportparts="dot: badge"><template shadowrootmode="closed"><span part="dot">Dot</span><b part="secret">Secret</b></template></x-inner></template></x-outer>');
        self::assertSame([1 => 1, 2 => 2], ShadowRoots::compose($document));
        $dot = $document->querySelector('span');
        self::assertSame('dot', $dot->getAttribute(Selector::PART_ATTRIBUTE.'1'));
        self::assertSame('badge', $dot->getAttribute(Selector::PART_ATTRIBUTE.'0'));
        self::assertNull($document->querySelector('b')->getAttribute(Selector::PART_ATTRIBUTE.'0'));
    }

    public function testOnlyFirstValidRootIsComposedAndOrdinaryTemplatesStayInert()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><x-card><template shadowrootmode="invalid">Invalid</template><template shadowrootmode="open">First</template><template shadowrootmode="closed">Second</template></x-card><input><template>Ordinary</template>');
        self::assertSame([1 => 1], ShadowRoots::compose($document));
        self::assertSame('First', $document->querySelector('x-card')->textContent);
        self::assertSame('Ordinary', $document->querySelector('body > template')->innerHTML);
    }
}
