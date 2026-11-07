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
 * @see https://adaptivecards.io/explorer/BackgroundImage.html
 */
final class BackgroundImage
{
    private array $options;

    public function __construct(string $url)
    {
        $this->options = [
            'type' => 'BackgroundImage',
            'url' => $url,
        ];
    }

    /**
     * @return $this
     */
    public function fillMode(ImageFillMode $fillMode): static
    {
        $this->options['fillMode'] = $fillMode->value;

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
    public function verticalAlignment(VerticalAlignment $verticalAlignment): static
    {
        $this->options['verticalAlignment'] = $verticalAlignment->value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
