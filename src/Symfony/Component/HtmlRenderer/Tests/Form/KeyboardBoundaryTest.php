<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class KeyboardBoundaryTest extends TestCase
{
    public function testNumberKeysAlignToStepsWithoutChangingDefaultAttributes()
    {
        foreach ([['.4', null, '0.7'], ['.7', '1', '.7']] as [$value, $max, $expected]) {
            $session = new HtmlRenderer()->createSession('<input type="number" value="'.$value.'" min=".2" step=".5"'.(null !== $max ? ' max="'.$max.'"' : '').'>', new RenderingContext(100, 100));
            $element = $session->getDocument()->querySelector('input');
            $session->focus($element);
            $session->key(new KeyboardEvent('ArrowUp'));
            self::assertSame($expected, $session->getValue($element));
            self::assertSame($value, $element->getAttribute('value'));
        }
    }

    public function testInvalidUtf8IsRejectedBeforeControlStateChanges()
    {
        $session = new HtmlRenderer()->createSession('<input value="original">', new RenderingContext(100, 100));
        $element = $session->getDocument()->querySelector('input');
        $session->focus($element);
        try {
            $session->input("\xff");
            self::fail('Invalid UTF-8 must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame('original', $session->getValue($element));
        }
    }

    public function testKeydownDisablingTheControlPreventsTheDefaultEdit()
    {
        $session = new HtmlRenderer()->createSession('<input value="A">', new RenderingContext(100, 100));
        $element = $session->getDocument()->querySelector('input');
        $session->focus($element);
        $session->on('keydown', static fn () => $element->setAttribute('disabled', ''));
        $session->key(new KeyboardEvent('Text', 'B'));
        self::assertSame('A', $session->getValue($element));
        self::assertNull($session->getFocusedElement());
    }

    public function testClickHandlerCanRemoveOrDisableItsTargetBeforeActivation()
    {
        foreach (['remove', 'disable'] as $action) {
            $session = new HtmlRenderer()->createSession('<form><button>Submit</button></form>', new RenderingContext(100, 100));
            $element = $session->getDocument()->querySelector('button');
            $session->on('click', static function () use ($element, $action): void {
                if ('remove' === $action) {
                    $element->remove();
                } else {
                    $element->setAttribute('disabled', '');
                }
            });
            self::assertNull($session->activate($element));
            self::assertNull($session->getFocusedElement());
        }
    }
}
