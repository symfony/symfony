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
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#dateinput
 */
final class DateInput extends AbstractInput
{
    private array $options = [];

    /**
     * @return $this
     */
    public function includeTime(bool $includeTime): static
    {
        $this->options['includeTime'] = $includeTime;

        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + $this->options + ['@type' => 'DateInput'];
    }
}
