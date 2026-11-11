<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

/**
 * @internal
 */
trait HttpDateTrait
{
    /**
     * Parses an HTTP-date in one of the three formats of RFC 9110 Section 5.6.7, in any letter case and with GMT, UTC or +0000 as zone.
     */
    private static function parseHttpDate(string $date): ?int
    {
        $date = trim($date);

        if (preg_match('/^([a-z]{3}), (\d\d) ([a-z]{3}) (\d{4}) (\d\d:\d\d:\d\d) (?:GMT|UTC|\+0000)$/i', $date, $m)) {
            [, $weekday, $day, $month, $year, $time] = $m;
        } elseif (preg_match('/^([a-z]{6,9}), (\d\d)-([a-z]{3})-(\d\d) (\d\d:\d\d:\d\d) (?:GMT|UTC|\+0000)$/i', $date, $m)) {
            [, $weekday, $day, $month, $year, $time] = $m;

            // RFC 9110: a two-digit year that is more than 50 years in the future is in the past century
            $year = (int) $year + 100 * intdiv($currentYear = (int) gmdate('Y'), 100);
            $year -= $year > $currentYear + 50 ? 100 : 0;
        } elseif (preg_match('/^([a-z]{3}) ([a-z]{3}) ([ \d]\d) (\d\d:\d\d:\d\d) (\d{4})$/i', $date, $m)) {
            [, $weekday, $month, $day, $time, $year] = $m;
        } else {
            return null;
        }

        if (false === $dateTime = \DateTimeImmutable::createFromFormat('!j M Y H:i:s', \sprintf('%d %s %d %s', $day, $month, $year, $time), new \DateTimeZone('UTC'))) {
            return null;
        }

        // reject out of range days or times, which PHP reports as warnings, and weekday names that don't match the date
        if (false !== \DateTimeImmutable::getLastErrors() || 0 !== strcasecmp($weekday, $dateTime->format(3 < \strlen($weekday) ? 'l' : 'D'))) {
            return null;
        }

        // on 32-bit PHP, dates after 2038 are clamped to the greatest timestamp, and dates before 1901 are invalid
        if (\PHP_INT_SIZE < 8 && $dateTime > new \DateTimeImmutable('@'.\PHP_INT_MAX)) {
            return \PHP_INT_MAX;
        }

        if (\PHP_INT_SIZE < 8 && $dateTime < new \DateTimeImmutable('@'.\PHP_INT_MIN)) {
            return null;
        }

        return $dateTime->getTimestamp();
    }
}
