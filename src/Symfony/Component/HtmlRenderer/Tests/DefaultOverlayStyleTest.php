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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class DefaultOverlayStyleTest extends TestCase
{
    public function testDefaultPopoverFitsItsContentAndDoesNotPaintAnOpaqueBackdrop()
    {
        $session = new HtmlRenderer()->createSession('<p>Page</p><div popover>Popup</div>', new RenderingContext(200, 120));
        $popover = $session->getDocument()->querySelector('[popover]');
        $session->showPopover($popover);
        $result = $session->render();
        $bounds = $result->getBounds($popover)[0];
        self::assertSame(40.0, $bounds->width);
        self::assertEqualsWithDelta(19.2, $bounds->height, 0.00001);
        self::assertSame(80.0, $bounds->x);
        self::assertEqualsWithDelta(50.4, $bounds->y, 0.00001);
        self::assertSame([], array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type'])));
    }

    public function testModalKeepsItsBackdropAndAuthorDimensionsOverrideDefaults()
    {
        $session = new HtmlRenderer()->createSession('<style>dialog{width:70px;height:30px}</style><dialog>Modal</dialog>', new RenderingContext(200, 120));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $result = $session->render();
        $bounds = $result->getBounds($dialog)[0];
        self::assertSame([70.0, 30.0, 65.0, 45.0], [$bounds->width, $bounds->height, $bounds->x, $bounds->y]);
        $rectangles = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
        self::assertCount(1, $rectangles);
        self::assertSame([200.0, 120.0], [$rectangles[0]['width'], $rectangles[0]['height']]);
    }

    public function testAuthorPopoverBackdropRetainsPriority()
    {
        $session = new HtmlRenderer()->createSession('<style>[popover]::backdrop{background:red}</style><div popover>Popup</div>', new RenderingContext(200, 120));
        $session->showPopover($session->getDocument()->querySelector('[popover]'));
        $rectangles = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
        self::assertCount(1, $rectangles);
        self::assertSame('red', $rectangles[0]['color']);
    }
}
