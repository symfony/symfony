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
 * @see https://adaptivecards.io/explorer/MediaSource.html
 */
final class MediaSource
{
    private array $options;

    public function __construct(string $url)
    {
        $this->options = [
            'type' => 'MediaSource',
            'url' => $url,
        ];
    }

    /**
     * @return $this
     */
    public function mimeType(string $mimeType): static
    {
        $this->options['mimeType'] = $mimeType;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
