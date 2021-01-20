<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateIntervalFormatter;

interface DateIntervalFormatterInterface
{
    /**
     * @param \DateInterval|string $interval
     */
    public function formatInterval($interval, int $precision = 0): string;

    /**
     * @param \DateTimeInterface|string      $dateTime
     * @param \DateTimeInterface|string|null $currentDateTime
     */
    public function formatRelative($dateTime, $currentDateTime = null, int $precision = 0): string;
}
