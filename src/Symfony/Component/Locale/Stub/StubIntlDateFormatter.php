<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

use Symfony\Component\Locale\Locale;

/**
 * Provides a stub IntlDateFormatter for the 'en' locale.
 */
class StubIntlDateFormatter
{
    /* formats */
    const NONE = -1;
    const FULL = 0;
    const LONG = 1;
    const MEDIUM = 2;
    const SHORT = 3;

    /* formats */
    const TRADITIONAL = 0;
    const GREGORIAN = 1;

    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = null)
    {
        if ('en' != $locale) {
            throw new \InvalidArgumentException('Unsupported $locale value. Only the \'en\' locale is supported. Install the intl extension for full localization capabilities.');
        }

        $this->setPattern($pattern);
    }

    public function format($timestamp)
    {
        $regExp = "/('(M+|y+|d+|[^Myd])|M+|y+|d+)/";

        $callback = function($matches) use ($timestamp) {
            $pattern = $matches[0];
            $length = strlen($pattern);

            if ("'" === $pattern[0]) {
                return substr($pattern, 1);
            }

            switch ($pattern[0]) {
                case 'M':
                    $matchLengthMap = array(
                        1   => 'n',
                        2   => 'm',
                        3   => 'M',
                        4   => 'F',
                    );

                    if (isset($matchLengthMap[$length])) {
                       return gmdate($matchLengthMap[$length], $timestamp);
                    } else if (5 == $length) {
                        return substr(gmdate('M', $timestamp), 0, 1);
                    } else {
                        return str_pad(gmdate('m', $timestamp), $length, '0', STR_PAD_LEFT);
                    }
                    break;

                case 'y':
                    $matchLengthMap = array(
                        1   => 'Y',
                        2   => 'y',
                        3   => 'Y',
                        4   => 'Y',
                    );

                    if (isset($matchLengthMap[$length])) {
                       return gmdate($matchLengthMap[$length], $timestamp);
                    } else {
                        return str_pad(gmdate('Y', $timestamp), $length, '0', STR_PAD_LEFT);
                    }
                    break;

                case 'd':
                    return str_pad(gmdate('j', $timestamp), $length, '0', STR_PAD_LEFT);
                    break;
            }  
        };

        $formatted = preg_replace_callback($regExp, $callback, $this->getPattern());

        return $formatted;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getCalendar()
    {
        $this->throwMethodNotImplementException(__METHOD__);
    }

    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
    }

    private function throwMethodNotImplementException($methodName)
    {
        $message = sprintf('The %s::%s() is not implemented. Install the intl extension for full localization capabilities.', __CLASS__, $methodName);
        throw new \RuntimeException($message);
    }
}
