<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class TimeZoneTransformer extends Transformer
{
    static protected $timezonesId = array();

    public function format(\DateTime $dateTime, $length)
    {
        return $dateTime->format('\G\M\TP');
    }

    public function getReverseMatchingRegExp($length)
    {
        return 'GMT[+-]\d{2}:\d{2}';
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'timezone' => $this->getTimezoneId($matched)
        );
    }

    protected function getTimezoneId($matched)
    {
        $offset = $this->getSecondsOffset($matched);

        if (isset(self::$timezonesId[$offset])) {
            return $timezonesId[$offset];
        }

        $abbreviations = \DateTimeZone::listAbbreviations();

        $timezoneId = null;
        foreach ($abbreviations as $zone => $timezones) {
            foreach ($timezones as $timezone) {
                if ($offset === $timezone['offset'] && 1 === preg_match('/^Etc\//', $timezone['timezone_id'])) {
                    $timezoneId = $timezone['timezone_id'];
                    break 2;
                }
            }
        }

        self::$timezonesId[$offset] = $timezoneId;
        return self::$timezonesId[$offset];
    }

    protected function getSecondsOffset($timezone)
    {
        preg_match('/GMT(?P<signal>[+-])(?P<hours>\d{2}):(?P<minutes>\d{2})/', $timezone, $matches);
        $seconds = ($matches['hours'] * 60 * 60) + ($matches['minutes'] * 60);
        $seconds *= $matches['signal'] == '-' ? -1 : 1;
        return $seconds;
    }
}
