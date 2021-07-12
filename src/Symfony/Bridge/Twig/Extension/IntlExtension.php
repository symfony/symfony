<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Intl\DateIntervalFormatter\DateIntervalFormatter;
use Symfony\Component\Intl\DateIntervalFormatter\DateIntervalFormatterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class IntlExtension extends AbstractExtension
{
    private $dateIntervalFormatter;

    public function __construct(DateIntervalFormatterInterface $dateIntervalFormatter = null)
    {
        $this->dateIntervalFormatter = $dateIntervalFormatter ?? new DateIntervalFormatter();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_interval', [$this->dateIntervalFormatter, 'formatInterval']),
            new TwigFilter('date_relative', [$this->dateIntervalFormatter, 'formatRelative']),
        ];
    }
}
