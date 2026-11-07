<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ChoiceInputStyle;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Data\Query;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input;

/**
 * @see https://adaptivecards.io/explorer/Input.ChoiceSet.html
 */
final class ChoiceSet extends Input
{
    public function __construct(string $id)
    {
        $this->options = [
            'type' => 'Input.ChoiceSet',
            'id' => $id,
        ];
    }

    /**
     * @return $this
     */
    public function choice(Choice $choice): static
    {
        $this->options['choices'][] = $choice->toArray();

        return $this;
    }

    /**
     * Loads the choices dynamically from a bot.
     *
     * @return $this
     */
    public function choicesData(Query $choicesData): static
    {
        $this->options['choices.data'] = $choicesData->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function isMultiSelect(bool $isMultiSelect): static
    {
        $this->options['isMultiSelect'] = $isMultiSelect;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(ChoiceInputStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }

    /**
     * @param string $value The value of the selected choice, or a comma-separated list of values when isMultiSelect is enabled
     *
     * @return $this
     */
    public function value(string $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function placeholder(string $placeholder): static
    {
        $this->options['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * @return $this
     */
    public function wrap(bool $wrap): static
    {
        $this->options['wrap'] = $wrap;

        return $this;
    }
}
