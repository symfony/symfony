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
 * @see https://adaptivecards.io/explorer/TextRun.html
 */
final class TextRun implements InlineInterface
{
    private array $options;

    /**
     * @param string $text The text to display, Markdown is not supported
     */
    public function __construct(string $text)
    {
        $this->options = [
            'type' => 'TextRun',
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
    public function highlight(bool $highlight): static
    {
        $this->options['highlight'] = $highlight;

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
    public function italic(bool $italic): static
    {
        $this->options['italic'] = $italic;

        return $this;
    }

    /**
     * @return $this
     */
    public function selectAction(SelectActionInterface $selectAction): static
    {
        $this->options['selectAction'] = $selectAction->toArray();

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
    public function strikethrough(bool $strikethrough): static
    {
        $this->options['strikethrough'] = $strikethrough;

        return $this;
    }

    /**
     * @return $this
     */
    public function underline(bool $underline): static
    {
        $this->options['underline'] = $underline;

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

    public function toArray(): array
    {
        return $this->options;
    }
}
