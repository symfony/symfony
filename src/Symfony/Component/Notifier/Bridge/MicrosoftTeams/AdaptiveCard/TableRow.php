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
 * @see https://adaptivecards.io/explorer/TableRow.html
 */
final class TableRow
{
    private array $options = ['type' => 'TableRow'];

    /**
     * @return $this
     */
    public function cell(TableCell $cell): static
    {
        $this->options['cells'][] = $cell->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function style(ContainerStyle $style): static
    {
        $this->options['style'] = $style->value;

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
