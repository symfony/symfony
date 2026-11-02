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
     * Adds a data point, or a series of data points for stacked and grouped bar charts.
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
    public function horizontalAlignment(HorizontalAlignment $horizontalAlignment): static
    {
        $this->options['horizontalAlignment'] = $horizontalAlignment->value;

        return $this;
    }
}
