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
 * @see https://adaptivecards.io/explorer/Table.html
 */
final class Table extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'Table',
        ];
    }

    /**
     * @return $this
     */
    public function column(TableColumnDefinition $column): static
    {
        $this->options['columns'][] = $column->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function row(TableRow $row): static
    {
        $this->options['rows'][] = $row->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function firstRowAsHeader(bool $firstRowAsHeader): static
    {
        $this->options['firstRowAsHeader'] = $firstRowAsHeader;

        return $this;
    }

    /**
     * @return $this
     */
    public function showGridLines(bool $showGridLines): static
    {
        $this->options['showGridLines'] = $showGridLines;

        return $this;
    }

    /**
     * @return $this
     */
    public function gridStyle(ContainerStyle $gridStyle): static
    {
        $this->options['gridStyle'] = $gridStyle->value;

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
}
