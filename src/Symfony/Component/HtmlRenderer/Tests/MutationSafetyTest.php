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
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class MutationSafetyTest extends TestCase
{
    public function testNestedPopoverCloseCallbackCannotAdoptTheOuterDefaultTarget()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><div id="outer" popover="manual"><div id="child" popover="hint">Child</div></div>', new RenderingContext(500, 200));
        $document = $session->getDocument();
        $outer = $document->getElementById('outer');
        $child = $document->getElementById('child');
        $session->showPopover($outer);
        $session->showPopover($child);
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $session->on('beforetoggle', static function () use ($other, $outer) {
            $other->querySelector('main')->appendChild($other->adoptNode($outer));
        }, $child);
        $session->hidePopover($outer);
        self::assertSame($other, $outer->ownerDocument);
        self::assertSame([], (new \ReflectionProperty($session, 'topLayers'))->getValue($session));
    }

    public function testReplacingPopoverRechecksTargetAfterClosingThePreviousPopover()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><div id="old" popover>Old</div><div id="new" popover>New</div>', new RenderingContext(500, 200));
        $document = $session->getDocument();
        $old = $document->getElementById('old');
        $new = $document->getElementById('new');
        $session->showPopover($old);
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $session->on('beforetoggle', static function () use ($other, $new) {
            $other->querySelector('main')->appendChild($other->adoptNode($new));
        }, $old);
        $session->showPopover($new);
        self::assertSame($other, $new->ownerDocument);
        self::assertSame([], (new \ReflectionProperty($session, 'topLayers'))->getValue($session));
    }

    public function testBeforeToggleAdoptionDoesNotMutateTheForeignDialog()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><dialog><button>OK</button></dialog>', new RenderingContext(500, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $other = $this->adoptOn($session, 'beforetoggle', $dialog);
        $session->close($dialog);
        self::assertSame($other, $dialog->ownerDocument);
        self::assertTrue($dialog->hasAttribute('open'));
        $session->render();
    }

    public function testCancelAdoptionStopsTheDialogCloseDefault()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><dialog></dialog>', new RenderingContext(500, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $other = $this->adoptOn($session, 'cancel', $dialog);
        $session->requestClose($dialog);
        self::assertSame($other, $dialog->ownerDocument);
        self::assertTrue($dialog->hasAttribute('open'));
        $session->render();
    }

    public function testToggleAdoptionStopsTheSubsequentCloseNotification()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><dialog></dialog>', new RenderingContext(500, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $other = $this->adoptOn($session, 'toggle', $dialog);
        $closes = 0;
        $session->on('close', static function () use (&$closes) { ++$closes; }, $dialog);
        $session->close($dialog);
        self::assertSame($other, $dialog->ownerDocument);
        self::assertFalse($dialog->hasAttribute('open'));
        self::assertSame(0, $closes);
        $session->render();
    }

    public function testSubmitAdoptionStopsTheDialogSubmissionDefault()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><dialog><form method="dialog"><button value="sent">OK</button></form></dialog>', new RenderingContext(500, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $form = $session->getDocument()->querySelector('form');
        $session->showModal($dialog);
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $session->on('submit', static function () use ($other, $dialog) {
            $other->querySelector('main')->appendChild($other->adoptNode($dialog));
        }, $form);
        self::assertNull($session->submit($form, $form->querySelector('button')));
        self::assertSame($other, $form->ownerDocument);
        self::assertTrue($dialog->hasAttribute('open'));
        $session->render();
    }

    public function testOptionChangeCanAdoptItsSelectDuringActivation()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><select size="3"><option>A</option><option>B</option></select>', new RenderingContext(500, 200));
        $select = $session->getDocument()->querySelector('select');
        $other = $this->adoptOn($session, 'change', $select);
        $session->activate($select->querySelectorAll('option')[1]);
        self::assertSame($other, $select->ownerDocument);
        self::assertNull($session->getFocusedElement());
        $session->render();
    }

    public function testPickerCommitCanAdoptItsSelectDuringChange()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><select><option>A</option><option>B</option></select>', new RenderingContext(500, 200));
        $select = $session->getDocument()->querySelector('select');
        $session->showPicker($select);
        $session->key(new KeyboardEvent('ArrowDown'));
        $other = $this->adoptOn($session, 'change', $select);
        $session->key(new KeyboardEvent('Enter'));
        self::assertSame($other, $select->ownerDocument);
        self::assertNull($session->getFocusedElement());
        $session->render();
    }

    public function testRangeKeyboardDoesNotDispatchForeignChangeAfterInput()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><input type="range" value="0">', new RenderingContext(500, 200));
        $range = $session->getDocument()->querySelector('input');
        $session->focus($range);
        $this->adoptOn($session, 'input', $range);
        $changes = 0;
        $session->on('change', static function () use (&$changes) { ++$changes; }, $range);
        $session->key(new KeyboardEvent('ArrowRight'));
        self::assertSame(0, $changes);
        self::assertNull($session->getFocusedElement());
        $session->render();
    }

    public function testRangePointerReleaseDoesNotReadForeignValueAfterInput()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><input type="range" value="0" style="width:200px;height:20px">', new RenderingContext(500, 200));
        $range = $session->getDocument()->querySelector('input');
        $track = $session->render()->getBounds($range, 'track')[0];
        $session->handlePointer(new PointerEvent('down', $track->x + 1, $track->y + 1));
        $this->adoptOn($session, 'input', $range);
        $changes = 0;
        $session->on('change', static function () use (&$changes) { ++$changes; }, $range);
        $session->handlePointer(new PointerEvent('up', $track->x + $track->width * .9, $track->y + 1));
        self::assertSame(0, $changes);
        self::assertNull($session->getFocusedElement());
        $session->render();
        $this->expectException(\InvalidArgumentException::class);
        $session->getValue($range);
    }

    private function adoptOn($session, string $event, \Dom\Element $element): \Dom\HTMLDocument
    {
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $session->on($event, static function () use ($other, $element) {
            $other->querySelector('main')->appendChild($other->adoptNode($element));
        }, $element);

        return $other;
    }
}
