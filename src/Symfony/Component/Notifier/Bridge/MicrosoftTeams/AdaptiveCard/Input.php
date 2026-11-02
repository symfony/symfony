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

abstract class Input extends Element
{
    /**
     * @return $this
     */
    public function errorMessage(string $errorMessage): static
    {
        $this->options['errorMessage'] = $errorMessage;

        return $this;
    }

    /**
     * @return $this
     */
    public function isRequired(bool $isRequired): static
    {
        $this->options['isRequired'] = $isRequired;

        return $this;
    }

    /**
     * @return $this
     */
    public function label(string $label): static
    {
        $this->options['label'] = $label;

        return $this;
    }

    /**
     * @return $this
     */
    public function labelPosition(InputLabelPosition $labelPosition): static
    {
        $this->options['labelPosition'] = $labelPosition->value;

        return $this;
    }

    /**
     * @param string|int $labelWidth A percentage of the input width as an integer, or a pixel width such as "40px"
     *
     * @return $this
     */
    public function labelWidth(string|int $labelWidth): static
    {
        $this->options['labelWidth'] = $labelWidth;

        return $this;
    }

    /**
     * @return $this
     */
    public function inputStyle(InputStyle $inputStyle): static
    {
        $this->options['inputStyle'] = $inputStyle->value;

        return $this;
    }
}
