<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

use Symfony\Component\Locale\Exception\NotImplementedException;

/**
 * Parser and formatter for time zone format
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class TimeZoneTransformer extends Transformer
{
    /**
     * {@inheritDoc}
     *
     * @throws NotImplementedException  When time zone is different than UTC or GMT (Etc/GMT)
     */
    public function format(\DateTime $dateTime, $length)
    {
        $timeZone = substr($dateTime->getTimezone()->getName(), 0, 3);

        if (!in_array($timeZone, array('Etc', 'UTC'))) {
            throw new NotImplementedException('Time zone different than GMT or UTC is not supported as a formatting output.');
        }

        return $dateTime->format('\G\M\TP');
    }

    /**
     * {@inheritDoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        return 'GMT[+-]\d{2}:?\d{2}';
    }

    /**
     * {@inheritDoc}
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'timezone' => self::getEtcTimeZoneId($matched)
        );
    }

    /**
     * Get an Etc/GMT timezone identifier for the specified timezone
     *
     * The PHP documentation for timezones states to not use the 'Other' time zones because them exists
     * "for backwards compatibility". However all Etc/GMT time zones are in the tz database 'etcetera' file,
     * which indicates they are not deprecated (neither are old names).
     *
     * Only GMT, Etc/Universal, Etc/Zulu, Etc/Greenwich, Etc/GMT-0, Etc/GMT+0 and Etc/GMT0 are old names and
     * are linked to Etc/GMT or Etc/UTC.
     *
     * @param  string  $timezone         A GMT timezone string (GMT-03:00, e.g.)
     * @return string                    A timezone identifier
     * @see    http://php.net/manual/en/timezones.others.php
     * @see    http://www.twinsun.com/tz/tz-link.htm
     * @throws NotImplementedException   When the GMT time zone have minutes offset different than zero
     * @throws InvalidArgumentException  When the value can not be matched with pattern
     */
    static public function getEtcTimeZoneId($formattedTimeZone)
    {
        if (preg_match('/GMT(?P<signal>[+-])(?P<hours>\d{2}):?(?P<minutes>\d{2})/', $formattedTimeZone, $matches)) {
            $hours   = (int) $matches['hours'];
            $minutes = (int) $matches['minutes'];
            $signal  = $matches['signal'] == '-' ? '+' : '-';

            if (0 < $minutes) {
                throw new NotImplementedException(sprintf(
                    'It is not possible to use a GMT time zone with minutes offset different than zero (0). GMT time zone tried: %s.',
                    $formattedTimeZone
                ));
            }

            return 'Etc/GMT' . ($hours != 0 ? $signal . $hours : '');
        }

        throw new \InvalidArgumentException('The GMT time zone \'%s\' does not match with the supported formats GMT[+-]HH:MM or GMT[+-]HHMM.');
    }
}
