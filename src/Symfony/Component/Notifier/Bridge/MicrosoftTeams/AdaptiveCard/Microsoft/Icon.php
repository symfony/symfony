<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Element;

/**
 * A Fluent UI icon, only rendered by Microsoft Teams.
 *
 * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-format#icons-in-adaptive-cards
 */
final class Icon extends Element
{
    /**
     * @param string $name The name of a Fluent UI icon, such as "Calendar"
     */
    public function __construct(string $name)
    {
        $this->options = [
            'type' => 'Icon',
            'name' => $name,
        ];
    }

    /**
     * @return $this
     */
    public function size(IconSize $size): static
    {
        $this->options['size'] = $size->value;

        return $this;
    }

    /**
     * @param string $style "Regular" or "Filled"
     *
     * @return $this
     */
    public function style(string $style): static
    {
        $this->options['style'] = $style;

        return $this;
    }

    /**
     * @return $this
     */
    public function color(IconColor $color): static
    {
        $this->options['color'] = $color->value;

        return $this;
    }
}
