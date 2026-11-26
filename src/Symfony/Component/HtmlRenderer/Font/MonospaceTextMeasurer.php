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

/**
 * Deterministic metrics for headless layout tests, without font shaping.
 * Letter spacing skips Arabic-script graphemes as a bounded cursive-text approximation.
 */
final class MonospaceTextMeasurer implements TextMeasurerInterface
{
    public function __construct(private readonly float $advanceRatio = 0.5, private readonly float $ascentRatio = 0.8)
    {
        if (!is_finite($advanceRatio) || $advanceRatio < 0 || !is_finite($ascentRatio) || $ascentRatio < 0 || $ascentRatio > 1) {
            throw new \InvalidArgumentException('Invalid font metric ratios.');
        }
    }

    public function measure(string $text, FontStyle $font): TextMetrics
    {
        $count = preg_match_all('/\X/u', $text);
        if (false === $count) {
            throw new \InvalidArgumentException('Text must be valid UTF-8.');
        }

        $width = $count * $font->size * $this->advanceRatio;
        if (0.0 !== $font->letterSpacing) {
            preg_match_all('/\X/u', $text, $matches);
            $tracked = 0;
            foreach ($matches[0] as $grapheme) {
                if (!preg_match('/\p{Arabic}/u', $grapheme)) {
                    ++$tracked;
                }
            }
            $width += $tracked * $font->letterSpacing;
        }
        if (0.0 !== $font->wordSpacing) {
            $width += substr_count($text, ' ') * $font->wordSpacing;
        }

        return new TextMetrics(max(0.0, $width), $font->size * $this->ascentRatio, $font->size * (1 - $this->ascentRatio));
    }
}
