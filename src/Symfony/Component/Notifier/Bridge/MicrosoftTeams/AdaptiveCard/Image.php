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
 * @see https://adaptivecards.io/explorer/Image.html
 */
final class Image extends Element
{
    public function __construct(string $url)
    {
        $this->options = [
            'type' => 'Image',
            'url' => $url,
        ];
    }

    /**
     * @return $this
     */
    public function altText(string $altText): static
    {
        $this->options['altText'] = $altText;

        return $this;
    }

    /**
     * @return $this
     */
    public function backgroundColor(string $backgroundColor): static
    {
        $this->options['backgroundColor'] = $backgroundColor;

        return $this;
    }

    /**
     * @param BlockElementHeight|string $height A pixel height such as "50px" is also accepted
     *
     * @return $this
     */
    public function height(BlockElementHeight|string $height): static
    {
        $this->options['height'] = \is_string($height) ? $height : $height->value;

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
    public function selectAction(SelectActionInterface $selectAction): static
    {
        $this->options['selectAction'] = $selectAction->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function size(ImageSize $size): static
    {
        $this->options['size'] = $size->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(ImageStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }

    /**
     * @param string $width A pixel width such as "50px"
     *
     * @return $this
     */
    public function width(string $width): static
    {
        $this->options['width'] = $width;

        return $this;
    }
}
