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

class DateIntervalFormatter implements DateIntervalFormatterInterface
{
    private const VALUE_NAMES = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    public function formatInterval($interval, int $precision = 0): string
    {
        if (!$interval instanceof \DateInterval) {
            $interval = new \DateInterval($interval);
        }

        $elements = [];
        $currentPrecision = 0;

        foreach (self::VALUE_NAMES as $value => $name) {
            if ($elements) {
                ++$currentPrecision;
            }

            if ((!$precision || $currentPrecision < $precision) && $interval->{$value}) {
                $elements[] = $this->format($interval->{$value}, $name);
            }
        }

        $lastElement = array_pop($elements);

        return null !== $lastElement ? ($elements ? implode(', ', $elements).' and '.$lastElement : $lastElement) : 'now';
    }

    /**
     * @param \DateTimeInterface|string      $dateTime
     * @param \DateTimeInterface|string|null $currentDateTime
     */
    public function formatRelative($dateTime, $currentDateTime = null, int $precision = 0): string
    {
        if (!$dateTime instanceof \DateTimeInterface) {
            $dateTime = new \DateTimeImmutable($dateTime);
        }

        if (!$currentDateTime instanceof \DateTimeInterface) {
            $currentDateTime = new \DateTimeImmutable($currentDateTime ?? 'now');
        }

        if ($dateTime > $currentDateTime) {
            return 'in '.$this->formatInterval($currentDateTime->diff($dateTime));
        }

        if ($dateTime->getTimestamp() === $currentDateTime->getTimestamp()) {
            return 'now';
        }

        return $this->formatInterval($currentDateTime->diff($dateTime), $precision).' ago';
    }

    private function format(int $number, string $singular): string
    {
        return $number > 1 ? "{$number} {$singular}s" : "{$number} {$singular}";
    }
}
