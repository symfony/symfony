<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * Flows the content of a container into columns, like CSS multi-column layout.
 *
 * The content is laid out at the width of a column, then cut into balanced
 * columns, the last one shorter, or, unbalanced, into columns of the height
 * of the container filled one after the other, side by side with the gap between them and
 * the rule drawn in its middle. A column ends where a break is allowed:
 * between blocks, or between the lines of a text block as orphans and widows
 * allow; what cannot be cut makes the columns taller.
 *
 * @experimental
 */
final class Columns
{
    public readonly ?int $count;
    public readonly ?int $width;
    public readonly int $gap;

    /**
     * @param int|null    $count   The number of columns, at most (null = as many as the width allows)
     * @param int|null    $width   The least width of a column (null = the room shared by the count)
     * @param int         $gap     The columns between two columns
     * @param string|null $rule    The character drawn between the columns, like column-rule (null = none)
     * @param Color|null  $color   The color of the rule (null = the text color)
     * @param bool        $balance Whether the columns share the content evenly, like column-fill: balance, or fill one
     *                             after the other up to the height of the container, like column-fill: auto
     */
    public function __construct(?int $count = null, ?int $width = null, int $gap = 2, public readonly ?string $rule = null, public readonly ?Color $color = null, public readonly bool $balance = true)
    {
        $this->count = null !== $count ? max(1, min(100, $count)) : null;
        $this->width = null !== $width ? max(1, min(10000, $width)) : null;
        $this->gap = max(0, min(100, $gap));
    }

    /**
     * The number of columns and their width in some room.
     *
     * @return array{int, int}
     */
    public function resolve(int $room): array
    {
        $room = max(1, $room);
        $fit = null !== $this->width ? max(1, intdiv($room + $this->gap, $this->width + $this->gap)) : null;
        $count = max(1, min($fit ?? $this->count ?? 1, $this->count ?? $fit ?? 1));
        $count = min($count, max(1, intdiv($room + $this->gap, 1 + $this->gap)));

        return [$count, max(1, intdiv($room - $this->gap * ($count - 1), $count))];
    }
}
