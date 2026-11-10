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
 * @see https://adaptivecards.io/explorer/Input.Toggle.html
 */
final class Toggle extends Input
{
    public function __construct(string $id, string $title)
    {
        $this->options = [
            'type' => 'Input.Toggle',
            'id' => $id,
            'title' => $title,
        ];
    }

    /**
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
    public function valueOff(string $valueOff): static
    {
        $this->options['valueOff'] = $valueOff;

        return $this;
    }

    /**
     * @return $this
     */
    public function valueOn(string $valueOn): static
    {
        $this->options['valueOn'] = $valueOn;

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
