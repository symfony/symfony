<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * MessageSelector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class MessageSelector
{
    /**
     * Given a message with different plural translations separated by a
     * pipe (|), this method returns the correct portion of the message based
     * on the given number, locale and the pluralization rules in the message
     * itself.
     *
     * The message supports two different types of pluralization rules:
     *
     * interval: {0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples
     * indexed:  There is one apple|There is %count% apples
     *
     * The indexed solution can also contain labels (e.g. one: There is one apple).
     * This is purely for making the translations more clear - it does not
     * affect the functionality.
     *
     * The two methods can also be mixed:
     *     {0} There is no apples|one: There is one apple|more: There is %count% apples
     *
     * @throws InvalidArgumentException
     * @param  string $message The message being translated
     * @param  integer $number The number of items represented for the message
     * @param  string $locale The locale to use for choosing
     * @return string
     *
     * @api
     */
    public function choose($message, $number, $locale)
    {
        $parts = explode('|', $message);
        $explicitRules = array();
        $standardRules = array();
        foreach ($parts as $part) {
            $part = trim($part);

            if (preg_match('/^(?<interval>'.Interval::getIntervalRegexp().')\s+(?<message>.+?)$/x', $part, $matches)) {
                $explicitRules[$matches['interval']] = $matches['message'];
            } elseif (preg_match('/^\w+\: +(.+)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }

        // try to match an explicit rule, then fallback to the standard ones
        foreach ($explicitRules as $interval => $m) {
            if (Interval::test($number, $interval)) {
                return $m;
            }
        }

        $position = PluralizationRules::get($number, $locale);
        if (!isset($standardRules[$position])) {
            throw new \InvalidArgumentException('Unable to choose a translation.');
        }

        return $standardRules[$position];
    }
}
