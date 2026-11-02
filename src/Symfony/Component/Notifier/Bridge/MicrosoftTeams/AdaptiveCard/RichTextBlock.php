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

/**
 * @see https://adaptivecards.io/explorer/RichTextBlock.html
 */
final class RichTextBlock extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'RichTextBlock',
            'inlines' => [],
        ];
    }

    /**
     * @return $this
     */
    public function inline(InlineInterface|string $inline): static
    {
        $this->options['inlines'][] = \is_string($inline) ? $inline : $inline->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function horizontalAlignment(HorizontalAlignment $horizontalAlignment): static
    {
        $this->options['horizontalAlignment'] = $horizontalAlignment->value;

        return $this;
    }
}
