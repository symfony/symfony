<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;

/** @internal */
final class EmbeddedDocuments
{
    public static function place(DisplayList $display, Rect $viewport, bool $clip = true): array
    {
        return [
            ...($clip ? [['type' => 'push-clip', 'x' => $viewport->x, 'y' => $viewport->y, 'width' => $viewport->width, 'height' => $viewport->height, 'clip-x' => true, 'clip-y' => true]] : []),
            ['type' => 'push-transform', 'matrix' => [1.0, 0.0, 0.0, 1.0, $viewport->x, $viewport->y]],
            ...$display->getOperations(),
            ['type' => 'pop-transform'],
            ...($clip ? [['type' => 'pop-clip']] : []),
        ];
    }
}
