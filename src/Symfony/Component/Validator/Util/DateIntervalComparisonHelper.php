<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Util;

/**
 * @internal
 */
final class DateIntervalComparisonHelper
{
    private function __construct()
    {
    }

    public static function supports($value, $comparedValue): bool
    {
        return $value instanceof \DateInterval && (
            \is_string($comparedValue) ||
            ($comparedValue instanceof \DateInterval && $value !== $comparedValue) ||
            0 === $comparedValue
        );
    }

    public static function convertValue(\DateTimeImmutable $reference, \DateInterval $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable(self::getMutableReference($reference))->add($value);
    }

    public static function convertComparedValue(\DateTimeImmutable $reference, $comparedValue): \DateTimeImmutable
    {
        if (\is_string($comparedValue)) {
            $reference = \DateTimeImmutable::createFromMutable(self::getMutableReference($reference));

            set_error_handler(function (int $errno, string $errstr): void {
                throw new \InvalidArgumentException($errstr);
            });

            try {
                return $reference->modify($comparedValue);
            } finally {
                restore_error_handler();
            }
        }

        if ($comparedValue instanceof \DateInterval) {
            return \DateTimeImmutable::createFromMutable(self::getMutableReference($reference)->add($comparedValue));
        }

        if (0 === $comparedValue) {
            return $reference;
        }

        throw new \LogicException();
    }

    private static function getMutableReference(\DateTimeImmutable $reference): \DateTime
    {
        if (\PHP_VERSION_ID >= 70300) {
            return \DateTime::createFromImmutable($reference);
        }

        return \DateTime::createFromFormat($format = 'U.u', $reference->format($format));
    }
}
