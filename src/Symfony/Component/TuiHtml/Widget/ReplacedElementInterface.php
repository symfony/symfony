<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

/**
 * A widget whose content has its own size, like an image: the renderer tells it the size its style asks for.
 *
 * @experimental
 */
interface ReplacedElementInterface
{
    /**
     * @param int|null                                   $columns  The width the style sets, like CSS width (null = its own)
     * @param int|null                                   $rows     The height the style sets, like CSS height or aspect-ratio (null = its own)
     * @param string|null                                $fit      How the content fills that box: fill, contain, cover, none or scale-down, like CSS object-fit
     * @param array{float, float, 2?: int, 3?: int}|null $position Where the content is in that box when it leaves room or is cut, fractions of
     *                                                             the room left from the left and the top, then cells further, like CSS object-position (null = centered)
     */
    public function setBox(?int $columns, ?int $rows, ?string $fit, ?array $position = null): void;
}
