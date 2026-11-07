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
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\SelectActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TextInputStyle;

/**
 * @see https://adaptivecards.io/explorer/Input.Text.html
 */
final class Text extends Input
{
    public function __construct(string $id)
    {
        $this->options = [
            'type' => 'Input.Text',
            'id' => $id,
        ];
    }

    /**
     * @return $this
     */
    public function isMultiline(bool $isMultiline): static
    {
        $this->options['isMultiline'] = $isMultiline;

        return $this;
    }

    /**
     * @return $this
     */
    public function maxLength(int $maxLength): static
    {
        $this->options['maxLength'] = $maxLength;

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
    public function regex(string $regex): static
    {
        $this->options['regex'] = $regex;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(TextInputStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function inlineAction(SelectActionInterface $inlineAction): static
    {
        $this->options['inlineAction'] = $inlineAction->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function value(string $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }
}
