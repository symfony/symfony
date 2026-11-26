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

class TransitionCommitTest extends TestCase
{
    public function testRangeChangeRequiresTheCurrentEditableRangeState()
    {
        foreach (['disabled', 'readonly', 'inert', 'type'] as $attribute) {
            $session = new HtmlRenderer()->createSession('<!DOCTYPE html><input type="range" value="0">', new RenderingContext(500, 200));
            $range = $session->getDocument()->querySelector('input');
            $session->focus($range);
            $session->on('input', static function () use ($range, $attribute) {
                $range->setAttribute($attribute, 'type' === $attribute ? 'text' : '');
            }, $range);
            $changes = 0;
            $session->on('change', static function () use (&$changes) { ++$changes; }, $range);
            $session->key(new KeyboardEvent('ArrowRight'));
            self::assertSame(0, $changes, $attribute);
        }
    }

    public function testPopoverAttributeRemovedDuringBeforeTogglePreventsOpening()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><div popover>Popup</div>', new RenderingContext(500, 200));
        $popover = $session->getDocument()->querySelector('div');
        $session->on('beforetoggle', static function () use ($popover) { $popover->removeAttribute('popover'); }, $popover);
        $session->showPopover($popover);
        self::assertSame([], (new \ReflectionProperty($session, 'topLayers'))->getValue($session));
    }

    public function testDialogOpenedByBeforeToggleListenerIsNotRegisteredAsModal()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><dialog>Dialog</dialog>', new RenderingContext(500, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->on('beforetoggle', static function () use ($dialog) { $dialog->setAttribute('open', ''); }, $dialog);
        $session->showModal($dialog);
        self::assertTrue($dialog->hasAttribute('open'));
        self::assertSame([], (new \ReflectionProperty($session, 'topLayers'))->getValue($session));
    }
}
