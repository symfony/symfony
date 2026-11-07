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
 * A chart, only rendered by Microsoft Teams.
 *
 * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/charts-in-adaptive-cards
 */
final class Chart extends Element
{
    public function __construct(ChartType $type)
    {
        $this->options = [
            'type' => 'Chart.'.$type->value,
        ];
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * Adds a data point, or a series of data points for stacked bar, grouped bar and line charts.
     *
     * Not used by gauge charts, see value() and segment().
     *
     * @return $this
     */
    public function data(ChartData|ChartDataPoint $data): static
    {
        $this->options['data'][] = $data->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function showLegend(bool $showLegend): static
    {
        $this->options['showLegend'] = $showLegend;

        return $this;
    }

    /**
     * @return $this
     */
    public function colorSet(string $colorSet): static
    {
        $this->options['colorSet'] = $colorSet;

        return $this;
    }

    /**
     * The color to use for all data points.
     *
     * @param string $color A color name such as "good" or "attention", or a hexadecimal color code
     *
     * @return $this
     */
    public function color(string $color): static
    {
        $this->options['color'] = $color;

        return $this;
    }

    /**
     * @return $this
     */
    public function xAxisTitle(string $xAxisTitle): static
    {
        $this->options['xAxisTitle'] = $xAxisTitle;

        return $this;
    }

    /**
     * @return $this
     */
    public function yAxisTitle(string $yAxisTitle): static
    {
        $this->options['yAxisTitle'] = $yAxisTitle;

        return $this;
    }

    /**
     * Only used by vertical bar charts.
     *
     * @return $this
     */
    public function showBarValues(bool $showBarValues): static
    {
        $this->options['showBarValues'] = $showBarValues;

        return $this;
    }

    /**
     * Only used by grouped vertical bar charts.
     *
     * @return $this
     */
    public function stacked(bool $stacked): static
    {
        $this->options['stacked'] = $stacked;

        return $this;
    }

    /**
     * Only used by horizontal bar charts.
     *
     * @return $this
     */
    public function displayMode(ChartDisplayMode $displayMode): static
    {
        $this->options['displayMode'] = $displayMode->value;

        return $this;
    }

    /**
     * The measurement of a gauge chart.
     *
     * @return $this
     */
    public function value(int|float $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }

    /**
     * Only used by gauge charts.
     *
     * @return $this
     */
    public function min(int|float $min): static
    {
        $this->options['min'] = $min;

        return $this;
    }

    /**
     * Only used by gauge charts.
     *
     * @return $this
     */
    public function max(int|float $max): static
    {
        $this->options['max'] = $max;

        return $this;
    }

    /**
     * Adds a segment to a gauge chart.
     *
     * @return $this
     */
    public function segment(GaugeSegment $segment): static
    {
        $this->options['segments'][] = $segment->toArray();

        return $this;
    }

    /**
     * Only used by gauge charts.
     *
     * @return $this
     */
    public function subLabel(string $subLabel): static
    {
        $this->options['subLabel'] = $subLabel;

        return $this;
    }

    /**
     * Only used by gauge charts.
     *
     * @return $this
     */
    public function showMinMax(bool $showMinMax): static
    {
        $this->options['showMinMax'] = $showMinMax;

        return $this;
    }

    /**
     * Only used by gauge charts.
     *
     * @return $this
     */
    public function valueFormat(GaugeValueFormat $valueFormat): static
    {
        $this->options['valueFormat'] = $valueFormat->value;

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
