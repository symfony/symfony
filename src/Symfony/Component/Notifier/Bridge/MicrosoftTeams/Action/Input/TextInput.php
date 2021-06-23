<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#textinput
 */
final class TextInput extends AbstractInput
{
    private $options = [];

    public function isMultiline(bool $multiline): self
    {
        $this->options['isMultiline'] = $multiline;

        return $this;
    }

    public function maxLength(int $maxLength): self
    {
        $this->options['maxLength'] = $maxLength;

        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + $this->options + ['@type' => 'TextInput'];
    }
}
