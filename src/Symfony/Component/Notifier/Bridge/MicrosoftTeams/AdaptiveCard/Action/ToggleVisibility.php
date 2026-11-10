<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\SelectActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TargetElement;

/**
 * @see https://adaptivecards.io/explorer/Action.ToggleVisibility.html
 */
final class ToggleVisibility extends Action implements SelectActionInterface
{
    public function __construct()
    {
        $this->options = [
            'type' => 'Action.ToggleVisibility',
            'targetElements' => [],
        ];
    }

    /**
     * Adds an element to toggle, given as a TargetElement or as the id of the element.
     *
     * @return $this
     */
    public function targetElement(TargetElement|string $targetElement): static
    {
        $this->options['targetElements'][] = \is_string($targetElement) ? $targetElement : $targetElement->toArray();

        return $this;
    }
}
