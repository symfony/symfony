<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Font;

final class TextMetrics
{
    /**
     * @param list<float>|null $caretPositions Visual advances indexed by logical grapheme boundary
     */
    public function __construct(public readonly float $width, public readonly float $ascent, public readonly float $descent, public readonly ?array $caretPositions = null)
    {
        if (!is_finite($width) || !is_finite($ascent) || !is_finite($descent) || $width < 0 || $ascent < 0 || $descent < 0) {
            throw new \InvalidArgumentException('Text metrics must be finite and non-negative.');
        }
        if (null !== $caretPositions && (!array_is_list($caretPositions) || array_any($caretPositions, static fn ($position): bool => !\is_int($position) && !\is_float($position) || !is_finite((float) $position)))) {
            throw new \InvalidArgumentException('Caret positions must be a list of finite advances.');
        }
    }
}
