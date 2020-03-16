<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter\DateFormat;

use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * Parser and formatter for time zone format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 */
class TimezoneTransformer extends Transformer
{
    /**
     * {@inheritdoc}
     *
     * @throws NotImplementedException When time zone is different than UTC or GMT (Etc/GMT)
     */
    public function format(\DateTime $dateTime, int $length): string
    {
        $timeZone = substr($dateTime->getTimezone()->getName(), 0, 3);

        if (!\in_array($timeZone, ['Etc', 'UTC', 'GMT'])) {
            throw new NotImplementedException('Time zone different than GMT or UTC is not supported as a formatting output.');
        }

        if ('Etc' === $timeZone) {
            // i.e. Etc/GMT+1, Etc/UTC, Etc/Zulu
            $timeZone = substr($dateTime->getTimezone()->getName(), 4);
        }

        // From ICU >= 59.1 GMT and UTC are no longer unified
        if (\in_array($timeZone, ['UTC', 'UCT', 'Universal', 'Zulu'])) {
            // offset is not supported with UTC
            return $length > 3 ? 'Coordinated Universal Time' : 'UTC';
        }

        $offset = (int) $dateTime->format('O');

        // From ICU >= 4.8, the zero offset is no more used, example: GMT instead of GMT+00:00
        if (0 === $offset) {
            return $length > 3 ? 'Greenwich Mean Time' : 'GMT';
        }

        if ($length > 3) {
            return $dateTime->format('\G\M\TP');
        }

        return sprintf('GMT%s%d', ($offset >= 0 ? '+' : ''), $offset / 100);
    }

    /**
     * {@inheritdoc}
     */
    public function getReverseMatchingRegExp(int $length): string
    {
        return 'GMT[+-]\d{2}:?\d{2}';
    }

    /**
     * {@inheritdoc}
     */
    public function extractDateOptions(string $matched, int $length): array
    {
        return [
            'timezone' => self::getEtcTimeZoneId($matched),
        ];
    }

    /**
     * Get an Etc/GMT timezone identifier for the specified timezone.
     *
     * The PHP documentation for timezones states to not use the 'Other' time zones because them exists
     * "for backwards compatibility". However all Etc/GMT time zones are in the tz database 'etcetera' file,
     * which indicates they are not deprecated (neither are old names).
     *
     * Only GMT, Etc/Universal, Etc/Zulu, Etc/Greenwich, Etc/GMT-0, Etc/GMT+0 and Etc/GMT0 are old names and
     * are linked to Etc/GMT or Etc/UTC.
     *
     * @param string $formattedTimeZone A GMT timezone string (GMT-03:00, e.g.)
     *
     * @return string A timezone identifier
     *
     * @see https://php.net/timezones.others
     *
     * @throws NotImplementedException   When the GMT time zone have minutes offset different than zero
     * @throws \InvalidArgumentException When the value can not be matched with pattern
     */
    public static function getEtcTimeZoneId(string $formattedTimeZone): string
    {
        if (preg_match('/GMT(?P<signal>[+-])(?P<hours>\d{2}):?(?P<minutes>\d{2})/', $formattedTimeZone, $matches)) {
            $hours = (int) $matches['hours'];
            $minutes = (int) $matches['minutes'];
            $signal = '-' === $matches['signal'] ? '+' : '-';

            if (0 < $minutes) {
                throw new NotImplementedException(sprintf('It is not possible to use a GMT time zone with minutes offset different than zero (0). GMT time zone tried: "%s".', $formattedTimeZone));
            }

            return 'Etc/GMT'.(0 !== $hours ? $signal.$hours : '');
        }

        throw new \InvalidArgumentException(sprintf('The GMT time zone "%s" does not match with the supported formats GMT[+-]HH:MM or GMT[+-]HHMM.', $formattedTimeZone));
    }
}
