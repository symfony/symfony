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
 * A segment of a gauge chart.
 */
final class GaugeSegment
{
    private array $options;

    public function __construct(int|float $size)
    {
        $this->options = ['size' => $size];
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
