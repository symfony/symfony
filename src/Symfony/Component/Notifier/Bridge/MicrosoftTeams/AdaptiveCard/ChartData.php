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
 * A named series of data points, used by stacked and grouped bar charts.
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
     * @return $this
     */
    public function data(ChartDataPoint $data): static
    {
        $this->options['data'][] = $data->toArray();

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
