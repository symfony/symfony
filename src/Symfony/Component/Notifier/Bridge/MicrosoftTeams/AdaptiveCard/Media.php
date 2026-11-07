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
 * @see https://adaptivecards.io/explorer/Media.html
 */
final class Media extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'Media',
            'sources' => [],
        ];
    }

    /**
     * @return $this
     */
    public function source(MediaSource $source): static
    {
        $this->options['sources'][] = $source->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function poster(string $poster): static
    {
        $this->options['poster'] = $poster;

        return $this;
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
    public function captionSource(CaptionSource $captionSource): static
    {
        $this->options['captionSources'][] = $captionSource->toArray();

        return $this;
    }
}
