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
 * A series of data points.
 *
 * Stacked horizontal bar charts use a title with legend/value points added via data();
 * line and grouped vertical bar charts use a legend with x/y points added via value().
 */
final class ChartData
{
    private array $options = [];

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function key(string $key): static
    {
        $this->options['key'] = $key;

        return $this;
    }

    /**
     * Adds a legend/value point to the series of a stacked horizontal bar chart.
     *
     * @return $this
     */
    public function data(ChartDataPoint $data): static
    {
        $this->options['data'][] = $data->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function legend(string $legend): static
    {
        $this->options['legend'] = $legend;

        return $this;
    }

    /**
     * Adds an x/y point to the series of a line or grouped vertical bar chart.
     *
     * @return $this
     */
    public function value(ChartDataPoint $value): static
    {
        $this->options['values'][] = $value->toArray();

        return $this;
    }

    /**
     * @param string $color A color name such as "good" or "attention", or a hexadecimal color code
     *
     * @return $this
     */
    public function color(string $color): static
    {
        $this->options['color'] = $color;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
