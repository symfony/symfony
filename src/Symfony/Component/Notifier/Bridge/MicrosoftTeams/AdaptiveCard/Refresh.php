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

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action\Execute;

/**
 * @see https://adaptivecards.io/explorer/Refresh.html
 */
final class Refresh
{
    private array $options = ['type' => 'Refresh'];

    /**
     * @return $this
     */
    public function action(Execute $action): static
    {
        $this->options['action'] = $action->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function expires(\DateTimeInterface $expires): static
    {
        $this->options['expires'] = $expires->format(\DateTimeInterface::RFC3339);

        return $this;
    }

    /**
     * Adds a user that gets the refreshed card automatically.
     *
     * @return $this
     */
    public function userId(string $userId): static
    {
        $this->options['userIds'][] = $userId;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
