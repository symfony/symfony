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
 * @see https://adaptivecards.io/explorer/TokenExchangeResource.html
 */
final class TokenExchangeResource
{
    private array $options;

    public function __construct(string $id, string $uri, string $providerId)
    {
        $this->options = [
            'type' => 'TokenExchangeResource',
            'id' => $id,
            'uri' => $uri,
            'providerId' => $providerId,
        ];
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
