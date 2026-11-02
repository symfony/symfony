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

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input;

/**
 * @see https://adaptivecards.io/explorer/Input.Number.html
 */
final class Number extends Input
{
    public function __construct(string $id)
    {
        $this->options = [
            'type' => 'Input.Number',
            'id' => $id,
        ];
    }

    /**
     * @return $this
     */
    public function max(int|float $max): static
    {
        $this->options['max'] = $max;

        return $this;
    }

    /**
     * @return $this
     */
    public function min(int|float $min): static
    {
        $this->options['min'] = $min;

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
    public function value(int|float $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }
}
