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
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class PointerInputTest extends TestCase
{
    public function testCapturedTextSelectionExtendsBeyondControlClip()
    {
        $session = (new HtmlRenderer())->createSession('<input value="abcde" style="width:24px;font-size:10px;line-height:10px;border:0;padding:0">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $bounds = $session->render()->getBounds($input)[0];
        $session->handlePointer(new PointerEvent('down', $bounds->x + 1, $bounds->y + 1));
        $session->handlePointer(new PointerEvent('move', $bounds->x + 100, $bounds->y + 1));
        $session->handlePointer(new PointerEvent('up', $bounds->x + 100, $bounds->y + 1));
        self::assertSame([0, 5], $session->getTextSelection($input));
    }

    public function testRangeUsesNormalizedDefaultsAndPointerDragSnapsToStep()
    {
        $session = (new HtmlRenderer())->createSession('<input type="range" min="10" max="30" step="5" style="width:106px">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        self::assertSame('20', $session->getValue($input));
        $changes = 0;
        $session->on('change', static function () use (&$changes) { ++$changes; });
        $bounds = $session->render()->getBounds($input, 'track')[0];
        $session->handlePointer(new PointerEvent('down', $bounds->x + $bounds->width / 4, $bounds->y + 1));
        self::assertSame('15', $session->getValue($input));
        $session->handlePointer(new PointerEvent('move', $bounds->x + $bounds->width, $bounds->y + 1));
        $session->handlePointer(new PointerEvent('up', $bounds->x + $bounds->width, $bounds->y + 1));
        self::assertSame('30', $session->getValue($input));
        self::assertSame(1, $changes);
    }

    public function testUnchangedPointerMovementReusesFrameButListenerMutationsAreDetected()
    {
        $session = (new HtmlRenderer())->createSession('<p>Text</p>', new RenderingContext(300, 200));
        $session->handlePointer(new PointerEvent('move', 1, 1));
        $first = $session->render();
        $session->handlePointer(new PointerEvent('move', 2, 1));
        self::assertSame($first, $session->render());
        $session->on('pointermove', static function ($event) { $event->target->setAttribute('style', 'color:red'); });
        $session->handlePointer(new PointerEvent('move', 2, 1));
        self::assertNotSame($first, $session->render());
        self::assertSame('red', $session->render()->getComputedStyle($session->getDocument()->querySelector('p'))['color']);
    }

    public function testPointerPressAndReleaseMaintainActiveStateAndActivateOnce()
    {
        $session = (new HtmlRenderer())->createSession('<style>input:active {color:red}</style><input type="checkbox">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $bounds = $session->render()->getBounds($input)[0];
        $x = $bounds->x + 1;
        $y = $bounds->y + 1;
        $clicks = 0;
        $session->on('click', static function () use (&$clicks) { ++$clicks; });
        $session->handlePointer(new PointerEvent('down', $x, $y));
        self::assertFalse($session->isChecked($input));
        self::assertSame('red', $session->render()->getComputedStyle($input)['color']);
        $session->handlePointer(new PointerEvent('up', $x, $y));
        self::assertTrue($session->isChecked($input));
        self::assertSame(1, $clicks);
        self::assertNotSame('red', $session->render()->getComputedStyle($input)['color']);
    }

    public function testPointerDragSelectsControlGraphemesWithoutCollapsingOnRelease()
    {
        $session = (new HtmlRenderer())->createSession('<input value="abcde" style="width:100px;font-size:20px">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $bounds = $session->render()->getBounds($input)[0];
        $session->handlePointer(new PointerEvent('down', $bounds->x + 1, $bounds->y + 1));
        $session->handlePointer(new PointerEvent('move', $bounds->x + 31, $bounds->y + 1));
        $session->handlePointer(new PointerEvent('up', $bounds->x + 31, $bounds->y + 1));
        self::assertSame([0, 3], $session->getTextSelection($input));
    }

    public function testOutsidePressLightDismissesAutoPopoverAndAnyDialogWithoutClickThrough()
    {
        $session = (new HtmlRenderer())->createSession('<button style="width:20px;height:20px">Outside</button><div popover style="width:40px;height:40px">Popup</div><dialog closedby="any" style="width:40px;height:40px"><input></dialog>', new RenderingContext(300, 200));
        $popover = $session->getDocument()->querySelector('[popover]');
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showPopover($popover);
        $session->handlePointer(new PointerEvent('down', 1, 1));
        self::assertSame([], $session->render()->getBounds($popover));
        $session->showModal($dialog);
        $clicks = 0;
        $session->on('click', static function () use (&$clicks) { ++$clicks; });
        $session->handlePointer(new PointerEvent('down', 1, 1));
        $session->handlePointer(new PointerEvent('up', 1, 1));
        self::assertFalse($dialog->hasAttribute('open'));
        self::assertSame(0, $clicks);
    }

    public function testPreventedPointerDownDoesNotActivateControl()
    {
        $session = (new HtmlRenderer())->createSession('<input type="checkbox">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->on('pointerdown', static fn ($event) => $event->preventDefault());
        $session->handlePointer(new PointerEvent('down', 1, 1));
        $session->handlePointer(new PointerEvent('up', 1, 1));
        self::assertFalse($session->isChecked($input));
    }
}
