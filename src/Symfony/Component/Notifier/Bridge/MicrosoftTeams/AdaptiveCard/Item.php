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

abstract class Item
{
    protected array $options = [];

    /**
     * @param array<string, string> $requires Feature names mapped to the minimum version the host must support
     *
     * @return $this
     */
    public function requires(array $requires): static
    {
        $this->options['requires'] = $requires;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
