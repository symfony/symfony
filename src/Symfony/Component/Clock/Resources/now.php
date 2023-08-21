<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock;

if (!\function_exists(now::class)) {
    /**
     * @throws \DateMalformedStringException When the modifier is invalid
     */
    function now(string $modifier = null): \DateTimeImmutable
    {
        if (null === $modifier || 'now' === $modifier) {
            return Clock::get()->now();
        }

        $now = Clock::get()->now();

        if (\PHP_VERSION_ID < 80300) {
            try {
                $tz = (new \DateTimeImmutable($modifier, $now->getTimezone()))->getTimezone();
            } catch (\Exception $e) {
                throw new \DateMalformedStringException($e->getMessage(), $e->getCode(), $e);
            }
            $now = $now->setTimezone($tz);

            return @$now->modify($modifier) ?: throw new \DateMalformedStringException(error_get_last()['message'] ?? sprintf('Invalid date modifier "%s".', $modifier));
        }

        $tz = (new \DateTimeImmutable($modifier, $now->getTimezone()))->getTimezone();

        return $now->setTimezone($tz)->modify($modifier);
    }
}
