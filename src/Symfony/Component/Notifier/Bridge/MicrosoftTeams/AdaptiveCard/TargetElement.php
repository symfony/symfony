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
 * @see https://adaptivecards.io/explorer/TargetElement.html
 */
final class TargetElement
{
    private array $options;

    public function __construct(string $elementId)
    {
        $this->options = [
            'type' => 'TargetElement',
            'elementId' => $elementId,
        ];
    }

    /**
     * Sets the visibility of the element instead of toggling it.
     *
     * @return $this
     */
    public function isVisible(bool $isVisible): static
    {
        $this->options['isVisible'] = $isVisible;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
