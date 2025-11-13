<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates whether a value is a valid MAC address.
 *
 * @author Ninos Ego <me@ninosego.de>
 */
class MacAddressValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MacAddress) {
            throw new UnexpectedTypeException($constraint, MacAddress::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (null !== $constraint->normalizer) {
            $value = ($constraint->normalizer)($value);
        }

        if (!self::checkMac($value, $constraint->type)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(MacAddress::INVALID_MAC_ERROR)
                ->addViolation();
        }
    }

    /**
     * Checks whether a MAC address is valid.
     */
    private static function checkMac(string $mac, string $type): bool
    {
        if (!filter_var($mac, \FILTER_VALIDATE_MAC)) {
            return false;
        }

        return match ($type) {
            MacAddress::ALL => true,
            MacAddress::ALL_NO_BROADCAST => !self::isBroadcast($mac),
            MacAddress::LOCAL_ALL => self::isLocal($mac),
            MacAddress::LOCAL_NO_BROADCAST => self::isLocal($mac) && !self::isBroadcast($mac),
            MacAddress::LOCAL_UNICAST => self::isLocal($mac) && self::isUnicast($mac),
            MacAddress::LOCAL_MULTICAST => self::isLocal($mac) && !self::isUnicast($mac),
            MacAddress::LOCAL_MULTICAST_NO_BROADCAST => self::isLocal($mac) && !self::isUnicast($mac) && !self::isBroadcast($mac),
            MacAddress::UNIVERSAL_ALL => !self::isLocal($mac),
            MacAddress::UNIVERSAL_UNICAST => !self::isLocal($mac) && self::isUnicast($mac),
            MacAddress::UNIVERSAL_MULTICAST => !self::isLocal($mac) && !self::isUnicast($mac),
            MacAddress::UNICAST_ALL => self::isUnicast($mac),
            MacAddress::MULTICAST_ALL => !self::isUnicast($mac),
            MacAddress::MULTICAST_NO_BROADCAST => !self::isUnicast($mac) && !self::isBroadcast($mac),
            MacAddress::BROADCAST => self::isBroadcast($mac),
        };
    }

    /**
     * Checks whether a MAC address is unicast or multicast.
     */
    private static function isUnicast(string $mac): bool
    {
        return match (self::sanitize($mac)[1]) {
            '0', '4', '8', 'c', '2', '6', 'a', 'e' => true,
            default => false,
        };
    }

    /**
     * Checks whether a MAC address is local or universal.
     */
    private static function isLocal(string $mac): bool
    {
        return match (self::sanitize($mac)[1]) {
            '2', '6', 'a', 'e', '3', '7', 'b', 'f' => true,
            default => false,
        };
    }

    /**
     * Checks whether a MAC address is broadcast.
     */
    private static function isBroadcast(string $mac): bool
    {
        return 'ffffffffffff' === self::sanitize($mac);
    }

    /**
     * Returns the sanitized MAC address.
     */
    private static function sanitize(string $mac): string
    {
        return strtolower(str_replace([':', '-', '.'], '', $mac));
    }
}
