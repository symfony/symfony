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

final readonly class TextSelection
{
    public function __construct(public int $anchor, public int $focus, public bool $caretVisible = true, public string $foreground = 'HighlightText', public string $background = 'Highlight')
    {
        if ($anchor < 0 || $focus < 0) {
            throw new \InvalidArgumentException('Selection offsets must be non-negative grapheme indexes.');
        }
    }
}
