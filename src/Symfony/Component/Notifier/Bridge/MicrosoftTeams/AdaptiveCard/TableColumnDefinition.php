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
 * @see https://adaptivecards.io/explorer/TableColumnDefinition.html
 */
final class TableColumnDefinition
{
    private array $options = ['type' => 'TableColumnDefinition'];

    /**
     * @param string|int $width A relative width as an integer, or a pixel width such as "50px"
     *
     * @return $this
     */
    public function width(string|int $width): static
    {
        $this->options['width'] = $width;

        return $this;
    }

    /**
     * @return $this
     */
    public function horizontalCellContentAlignment(HorizontalAlignment $horizontalCellContentAlignment): static
    {
        $this->options['horizontalCellContentAlignment'] = $horizontalCellContentAlignment->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function verticalCellContentAlignment(VerticalAlignment $verticalCellContentAlignment): static
    {
        $this->options['verticalCellContentAlignment'] = $verticalCellContentAlignment->value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
