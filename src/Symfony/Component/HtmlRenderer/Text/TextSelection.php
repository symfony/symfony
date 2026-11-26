<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

final class TextSelection
{
    public function __construct(public readonly int $anchor, public readonly int $focus, public readonly bool $caretVisible = true, public readonly string $foreground = 'HighlightText', public readonly string $background = 'Highlight')
    {
        if ($anchor < 0 || $focus < 0) {
            throw new \InvalidArgumentException('Selection offsets must be non-negative grapheme indexes.');
        }
    }
}
