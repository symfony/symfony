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

abstract class ToggleableItem extends Item
{
    /**
     * @return $this
     */
    public function id(string $id): static
    {
        $this->options['id'] = $id;

        return $this;
    }

    /**
     * @return $this
     */
    public function isVisible(bool $isVisible): static
    {
        $this->options['isVisible'] = $isVisible;

        return $this;
    }
}
