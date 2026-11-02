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
 * @see https://adaptivecards.io/explorer/CaptionSource.html
 */
final class CaptionSource
{
    private array $options;

    public function __construct(string $mimeType, string $url, string $label)
    {
        $this->options = [
            'type' => 'CaptionSource',
            'mimeType' => $mimeType,
            'url' => $url,
            'label' => $label,
        ];
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
