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
 * @see https://adaptivecards.io/explorer/Metadata.html
 */
final class Metadata
{
    private array $options = ['type' => 'Metadata'];

    /**
     * @return $this
     */
    public function webUrl(string $webUrl): static
    {
        $this->options['webUrl'] = $webUrl;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
