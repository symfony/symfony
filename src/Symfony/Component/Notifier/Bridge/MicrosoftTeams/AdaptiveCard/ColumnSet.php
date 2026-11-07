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
 * @see https://adaptivecards.io/explorer/ColumnSet.html
 */
final class ColumnSet extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'ColumnSet',
        ];
    }

    /**
     * @return $this
     */
    public function column(Column $column): static
    {
        $this->options['columns'][] = $column->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function selectAction(SelectActionInterface $selectAction): static
    {
        $this->options['selectAction'] = $selectAction->toArray();

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
    public function bleed(bool $bleed): static
    {
        $this->options['bleed'] = $bleed;

        return $this;
    }

    /**
     * @return $this
     */
    public function minHeight(string $minHeight): static
    {
        $this->options['minHeight'] = $minHeight;

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
