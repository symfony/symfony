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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#multichoiceinput
 */
final class MultiChoiceInput extends AbstractInput
{
    private const STYLES = [
        'expanded',
        'normal',
    ];

    private array $options = [];

    /**
     * @return $this
     */
    public function choice(string $display, string $value): static
    {
        $this->options['choices'][] = ['display' => $display, 'value' => $value];

        return $this;
    }

    /**
     * @return $this
     */
    public function isMultiSelect(bool $multiSelect): static
    {
        $this->options['isMultiSelect'] = $multiSelect;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(string $style): static
    {
        if (!\in_array($style, self::STYLES)) {
            throw new InvalidArgumentException(sprintf('Supported styles for "%s" method are: "%s".', __METHOD__, implode('", "', self::STYLES)));
        }

        $this->options['style'] = $style;

        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + $this->options + ['@type' => 'MultichoiceInput'];
    }
}
