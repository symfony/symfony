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
 * @see https://adaptivecards.io/explorer/Input.Time.html
 */
final class Time extends Input
{
    private const FORMAT = 'H:i';

    public function __construct(string $id)
    {
        $this->options = [
            'type' => 'Input.Time',
            'id' => $id,
        ];
    }

    /**
     * @return $this
     */
    public function max(\DateTimeInterface $max): static
    {
        $this->options['max'] = $max->format(self::FORMAT);

        return $this;
    }

    /**
     * @return $this
     */
    public function min(\DateTimeInterface $min): static
    {
        $this->options['min'] = $min->format(self::FORMAT);

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
    public function value(\DateTimeInterface $value): static
    {
        $this->options['value'] = $value->format(self::FORMAT);

        return $this;
    }
}
