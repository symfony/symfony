<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Block;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SlackActionsBlock extends AbstractSlackBlock
{
    public function __construct()
    {
        $this->options['type'] = 'actions';
    }

    /**
     * @return $this
     */
    public function button(string $text, string $url, ?string $style = null): static
    {
        if (25 === \count($this->options['elements'] ?? [])) {
            throw new \LogicException('Maximum number of buttons should not exceed 25.');
        }

        $element = new SlackButtonBlockElement($text, $url, $style);

        $this->options['elements'][] = $element->toArray();

        return $this;
    }
}
