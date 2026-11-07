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

enum ChartType: string
{
    case HorizontalBar = 'HorizontalBar';
    case HorizontalBarStacked = 'HorizontalBar.Stacked';
    case Line = 'Line';
    case Pie = 'Pie';
    case Donut = 'Donut';
    case Gauge = 'Gauge';
    case VerticalBar = 'VerticalBar';
    case VerticalBarGrouped = 'VerticalBar.Grouped';
}
