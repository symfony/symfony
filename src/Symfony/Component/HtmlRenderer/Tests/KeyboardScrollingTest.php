<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class KeyboardScrollingTest extends TestCase
{
    public function testPageAndArrowKeysScrollTheNearestPortAndCanBeCancelled()
    {
        $session = new HtmlRenderer()->createSession('<div id="box" style="width:72px;height:64px;overflow:auto"><button id="go">Go</button><div style="width:200px;height:300px">Tail</div></div>', new RenderingContext(300, 200));
        $box = $session->getDocument()->getElementById('box');
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame([0.0, 48.0], $session->getScroll($box));
        $session->key(new KeyboardEvent('PageUp'));
        self::assertSame([0.0, 0.0], $session->getScroll($box));
        $session->focus($session->getDocument()->getElementById('go'));
        $session->key(new KeyboardEvent('ArrowRight'));
        self::assertSame([18.0, 0.0], $session->getScroll($box));
        $session->on('keydown', static fn ($event) => $event->preventDefault());
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame([18.0, 0.0], $session->getScroll($box));
    }

    public function testTextareaPagesItsCaretAndSelectionWithoutScrollingItsAncestor()
    {
        $session = new HtmlRenderer()->createSession('<style>html{line-height:16px}</style><div id="outer" style="height:64px;overflow:auto"><textarea id="edit" style="height:32px;border:0;padding:0">00'."\n".'11'."\n".'22'."\n".'33'."\n".'44'."\n".'55</textarea><div style="height:300px">Tail</div></div>', new RenderingContext(300, 200));
        $edit = $session->getDocument()->getElementById('edit');
        $outer = $session->getDocument()->getElementById('outer');
        $session->focus($edit);
        $session->setTextSelection($edit, 0, 0);
        $text = $session->getValue($edit);
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame([6, 6], $session->getTextSelection($edit));
        self::assertSame([0.0, 0.0], $session->getScroll($outer));
        self::assertGreaterThan(0, $session->getScroll($edit)[1]);
        $carets = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn ($operation) => 'caret' === $operation['type']));
        $geometry = $session->render()->getScrollGeometry($edit);
        self::assertGreaterThanOrEqual($geometry->port->y, $carets[array_key_last($carets)]['y']);
        self::assertLessThanOrEqual($geometry->port->y + $geometry->port->height, $carets[array_key_last($carets)]['y'] + $carets[array_key_last($carets)]['height']);
        $session->key(new KeyboardEvent('PageDown', null, true));
        self::assertSame([6, 12], $session->getTextSelection($edit));
        $edit->setAttribute('readonly', '');
        $session->key(new KeyboardEvent('PageUp'));
        self::assertSame([6, 6], $session->getTextSelection($edit));
        self::assertSame($text, $session->getValue($edit));
        self::assertSame([0.0, 0.0], $session->getScroll($outer));
    }

    public function testListboxPagesUseItsFittedWindowInsteadOfScrollingAnAncestor()
    {
        $options = '';
        foreach (range('A', 'F') as $value) {
            $options .= '<option>'.$value.'</option>';
        }
        $session = new HtmlRenderer()->createSession('<style>html{line-height:16px}</style><div id="outer" style="height:64px;overflow:auto"><select id="list" size="2" style="border:0;padding:0">'.$options.'</select><div style="height:300px">Tail</div></div>', new RenderingContext(300, 200));
        $list = $session->getDocument()->getElementById('list');
        $session->focus($list);
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame(['C'], $session->getSelectedValues($list));
        $session->key(new KeyboardEvent('ArrowRight'));
        self::assertSame(['D'], $session->getSelectedValues($list));
        $session->key(new KeyboardEvent('PageUp'));
        self::assertSame(['B'], $session->getSelectedValues($list));
        self::assertSame([0.0, 0.0], $session->getScroll($session->getDocument()->getElementById('outer')));
    }

    public function testInputAndRangeOwnedKeysNeverScrollTheirAncestors()
    {
        $session = new HtmlRenderer()->createSession('<div id="box" style="width:72px;height:64px;overflow:auto"><input id="text" value="ABC"><input id="range" type="range" value="10"><div style="width:200px;height:300px">Tail</div></div>', new RenderingContext(300, 200));
        $box = $session->getDocument()->getElementById('box');
        $text = $session->getDocument()->getElementById('text');
        $session->focus($text);
        $session->key(new KeyboardEvent('ArrowLeft'));
        self::assertSame(0.0, $session->getScroll($box)[0]);
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame(48.0, $session->getScroll($box)[1]);
        $range = $session->getDocument()->getElementById('range');
        $session->focus($range);
        $before = $session->getScroll($box);
        $session->key(new KeyboardEvent('PageUp'));
        self::assertSame('20', $session->getValue($range));
        self::assertSame($before, $session->getScroll($box));
    }

    public function testTypingAndArrowNavigationKeepTheTextareaCaretVisible()
    {
        $session = new HtmlRenderer()->createSession('<style>html{line-height:16px}</style><textarea id="edit" style="height:32px;border:0;padding:0"></textarea>', new RenderingContext(300, 200));
        $edit = $session->getDocument()->getElementById('edit');
        $session->focus($edit);
        $session->key(new KeyboardEvent('Unidentified', "0\n1\n2\n3"));
        self::assertGreaterThan(0, $session->getScroll($edit)[1]);
        $session->key(new KeyboardEvent('Home', null, false, true));
        self::assertSame(0.0, $session->getScroll($edit)[1]);
        self::assertSame([0, 0], $session->getTextSelection($edit));
    }

    public function testRemovedOrAdoptedControlsAreNotRevealedAfterInputCallbacks()
    {
        foreach ([false, true] as $adopt) {
            $session = new HtmlRenderer()->createSession('<textarea></textarea>', new RenderingContext(300, 200));
            $edit = $session->getDocument()->querySelector('textarea');
            $session->focus($edit);
            $focusEvents = 0;
            $session->on('focus', static function () use (&$focusEvents): void { ++$focusEvents; });
            $session->on('input', static function () use ($edit, $adopt): void {
                if ($adopt) {
                    $other = \Dom\HTMLDocument::createFromString('<!doctype html><body>Other</body>');
                    $other->body->appendChild($other->adoptNode($edit));
                } else {
                    $edit->remove();
                }
            });
            $session->key(new KeyboardEvent('Unidentified', 'X'));
            self::assertNull($session->getFocusedElement());
            self::assertSame(0, $focusEvents);
        }
    }

    public function testMultiselectPagingMovesTheActiveOptionWithoutChangingCheckedValues()
    {
        $session = new HtmlRenderer()->createSession('<style>html{line-height:16px}option:picker-active{font-weight:700}</style><select multiple size="2" style="border:0;padding:0"><option selected>A</option><option>B</option><option>C</option><option>D</option></select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $options = $select->querySelectorAll('option');
        $session->focus($select);
        $session->key(new KeyboardEvent('PageDown'));
        self::assertSame(['A'], $session->getSelectedValues($select));
        self::assertSame('700', $session->render()->getComputedStyle($options->item(2))['font-weight']);
        $session->key(new KeyboardEvent('Space'));
        self::assertSame(['A', 'C'], $session->getSelectedValues($select));
        $session->key(new KeyboardEvent('PageUp'));
        self::assertSame(['A', 'C'], $session->getSelectedValues($select));
        $session->key(new KeyboardEvent('Space'));
        self::assertSame(['C'], $session->getSelectedValues($select));
    }

    public function testDeselectedOrInvalidActiveOptionsCannotMutateStaleSelections()
    {
        foreach (['disabled', 'removed', 'cancelled'] as $change) {
            $session = new HtmlRenderer()->createSession('<style>html{line-height:16px}</style><select multiple size="2" style="border:0;padding:0"><option selected>A</option><option>B</option><option>C</option></select>', new RenderingContext(300, 200));
            $select = $session->getDocument()->querySelector('select');
            $active = $select->querySelectorAll('option')->item(2);
            $session->focus($select);
            $session->key(new KeyboardEvent('PageDown'));
            self::assertSame(['A'], $session->getSelectedValues($select));
            $session->key(new KeyboardEvent('Space'));
            $session->key(new KeyboardEvent('Space'));
            self::assertSame(['A'], $session->getSelectedValues($select), 'The active option can remain unchecked.');
            $session->on('keydown', static function ($event) use ($active, $change): void {
                match ($change) {
                    'disabled' => $active->setAttribute('disabled', ''),
                    'removed' => $active->remove(),
                    default => $event->preventDefault(),
                };
            });
            $session->key(new KeyboardEvent('Space'));
            self::assertSame(['A'], $session->getSelectedValues($select));
        }
    }

    public function testKeyboardPagingChainsAcrossFramesAndHonorsChildContainment()
    {
        foreach ([false, true] as $contained) {
            $inner = ($contained ? '<style>html{overscroll-behavior:contain}</style>' : '').'<button>Inside</button><div style="height:120px">Rows</div>';
            $session = new HtmlRenderer()->createSession('<div id="outer" style="height:80px;overflow:auto"><iframe style="display:block;width:100px;height:40px;border:0" srcdoc="'.htmlspecialchars($inner).'"></iframe><div style="height:200px">Tail</div></div>', new RenderingContext(300, 200));
            $frame = $session->getDocument()->querySelector('iframe');
            $child = $session->getEmbeddedSession($frame);
            $session->focusNext();
            $childRoot = $child->getDocument()->documentElement;
            $geometry = $child->render()->getScrollGeometry($childRoot);
            $child->scroll($childRoot, 0, $geometry->maxY);
            $session->key(new KeyboardEvent('PageDown'));
            self::assertSame([0.0, $contained ? 0.0 : 64.0], $session->getScroll($session->getDocument()->getElementById('outer')));
        }
    }
}
