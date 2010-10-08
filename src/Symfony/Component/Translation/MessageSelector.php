<?php

namespace Symfony\Component\Translation;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * MessageSelector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class MessageSelector
{
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
