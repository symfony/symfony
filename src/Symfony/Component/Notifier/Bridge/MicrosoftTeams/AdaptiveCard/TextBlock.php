<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/TextBlock.html
 */
final class TextBlock extends Element
{
    /**
     * @param string $text The text to display, with a subset of Markdown
     */
    public function __construct(string $text)
    {
        $this->options = [
            'type' => 'TextBlock',
            'text' => $text,
        ];
    }

    /**
     * @return $this
     */
    public function color(Color $color): static
    {
        $this->options['color'] = $color->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function fontType(FontType $fontType): static
    {
        $this->options['fontType'] = $fontType->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function horizontalAlignment(HorizontalAlignment $horizontalAlignment): static
    {
        $this->options['horizontalAlignment'] = $horizontalAlignment->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function isSubtle(bool $isSubtle): static
    {
        $this->options['isSubtle'] = $isSubtle;

        return $this;
    }

    /**
     * @return $this
     */
    public function maxLines(int $maxLines): static
    {
        $this->options['maxLines'] = $maxLines;

        return $this;
    }

    /**
     * @return $this
     */
    public function size(FontSize $size): static
    {
        $this->options['size'] = $size->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function weight(FontWeight $weight): static
    {
        $this->options['weight'] = $weight->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function wrap(bool $wrap): static
    {
        $this->options['wrap'] = $wrap;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(TextBlockStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }
}
