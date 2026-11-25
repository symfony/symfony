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

final class PickerTest extends TestCase
{
    public function testAdoptingTheFocusedPickerIntoAnotherDocumentClearsSessionFocus()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>A</option></select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $session->showPicker($select);
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $other->querySelector('main')->appendChild($other->adoptNode($select));
        self::assertTrue($select->isConnected);
        self::assertNull($session->getFocusedElement());
        self::assertSame([], $session->render()->getBounds($select));
    }

    public function testFocusListenerCanAdoptAnOpeningPickerWithoutForeignToggleEvents()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>A</option></select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $events = [];
        $session->on('toggle', static function ($event) use (&$events) { $events[] = $event->type; });
        $session->on('focus', static function () use ($other, $select) {
            $other->querySelector('main')->appendChild($other->adoptNode($select));
        }, $select);
        $session->showPicker($select);
        self::assertNull($session->getFocusedElement());
        self::assertSame([], $events);
    }

    public function testFocusListenerCanCancelPickerOpeningWithoutStaleOpenEvent()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>A</option></select><button>Other</button>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $button = $session->getDocument()->querySelector('button');
        $states = [];
        $session->on('focus', static fn () => $session->focus($button), $select);
        $session->on('toggle', static function ($event) use (&$states) { $states[] = $event->data['newState']; }, $select);
        $session->showPicker($select);
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
        self::assertSame(['closed'], $states);
    }

    public function testHidingPopoverAlsoClosesItsNestedPicker()
    {
        $session = (new HtmlRenderer())->createSession('<div popover><select><option>A</option></select></div>', new RenderingContext(300, 200));
        $popover = $session->getDocument()->querySelector('div');
        $select = $session->getDocument()->querySelector('select');
        $session->showPopover($popover);
        $session->showPicker($select);
        $session->hidePopover($popover);
        $session->showPopover($popover);
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
    }

    public function testPickerCommitsOnlyOnConfirmationAndEscapeKeepsOriginalValue()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>A</option><option>B</option><option>C</option></select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $session->focus($select);
        $changes = 0;
        $session->on('change', static function () use (&$changes) { ++$changes; });
        $session->key(new KeyboardEvent('ArrowDown'));
        self::assertNotEmpty($session->render()->getBounds($select, 'picker'));
        $session->key(new KeyboardEvent('ArrowDown'));
        self::assertSame('A', $session->getValue($select));
        $session->key(new KeyboardEvent('Escape'));
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
        self::assertSame('A', $session->getValue($select));
        self::assertSame(0, $changes);
        $session->key(new KeyboardEvent('Enter'));
        $session->key(new KeyboardEvent('End'));
        $session->key(new KeyboardEvent('Enter'));
        self::assertSame('C', $session->getValue($select));
        self::assertSame(1, $changes);
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
        self::assertSame($select, $session->getFocusedElement());
    }

    public function testPickerSelectionByNativeOptionClosesAndFocusLeavingCancels()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>A</option><option>B</option></select><button>Other</button>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $session->activate($select);
        $session->activate($select->querySelectorAll('option')[1]);
        self::assertSame('B', $session->getValue($select));
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
        $session->showPicker($select);
        $session->key(new KeyboardEvent('Home'));
        $session->focus($session->getDocument()->querySelector('button'));
        self::assertSame('B', $session->getValue($select));
        self::assertSame([], $session->render()->getBounds($select, 'picker'));
    }

    public function testPickerKeyboardHighlightScrollsWithinItsOwnPort()
    {
        $session = (new HtmlRenderer())->createSession('<style>select::picker(select){max-height:40px}</style><select>'.implode('', array_map(static fn ($i) => '<option>'.$i.'</option>', range(1, 20))).'</select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $session->showPicker($select);
        $session->key(new KeyboardEvent('End'));
        $geometry = $session->render()->getScrollGeometry($select, 'picker');
        self::assertGreaterThan(0, $geometry->offsetY);
        $option = $select->querySelectorAll('option')[19];
        $bounds = $session->render()->getBounds($option)[0];
        self::assertGreaterThanOrEqual($geometry->port->y, $bounds->y);
        self::assertEqualsWithDelta($geometry->port->y + $geometry->port->height, $bounds->y + $bounds->height, 1.0E-9);
    }
}
