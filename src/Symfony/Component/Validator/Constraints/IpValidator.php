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
 * Validates whether a value is a valid IP address.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 * @author Ninos Ego <me@ninosego.de>
 */
class IpValidator extends ConstraintValidator
{
    /**
     * IPv4 subnets that cannot be reached over the public internet because they are special-purpose.
     *
     * Taken from the IANA IPv4 Special-Purpose Address Registry (RFC 6890), keeping the entries that
     * are not globally reachable, plus the multicast range. filter_var() covers some of them under
     * FILTER_FLAG_NO_PRIV_RANGE and others under FILTER_FLAG_NO_RES_RANGE, none under both, and
     * ignores the other ones altogether.
     */
    private const NON_PUBLIC_V4_SUBNETS = [
        '0.0.0.0/8',       // "this" network, including the unspecified address
        '10.0.0.0/8',      // private use
        '100.64.0.0/10',   // shared address space
        '127.0.0.0/8',     // loopback
        '169.254.0.0/16',  // link local
        '172.16.0.0/12',   // private use
        '192.0.0.0/24',    // IETF protocol assignments
        '192.0.2.0/24',    // documentation TEST-NET-1
        '192.88.99.0/24',  // 6to4 relay anycast, deprecated
        '192.168.0.0/16',  // private use
        '198.18.0.0/15',   // benchmarking
        '198.51.100.0/24', // documentation TEST-NET-2
        '203.0.113.0/24',  // documentation TEST-NET-3
        '224.0.0.0/4',     // multicast
        '240.0.0.0/4',     // reserved, including the broadcast address
    ];

    /**
     * IPv6 subnets that cannot be reached over the public internet, either because they
     * are special-purpose or because they carry a non-public IPv4 address.
     *
     * Taken from the IANA IPv6 Special-Purpose Address Registry (RFC 6890), keeping the entries
     * that are not globally reachable, plus the multicast range. "::/128" is left out since "::/96"
     * already covers it.
     */
    private const NON_PUBLIC_V6_SUBNETS = [
        '::1/128',        // loopback
        'fc00::/7',       // unique local address
        'fe80::/10',      // link local address
        'fec0::/10',      // site local address, deprecated by RFC 3879
        '::ffff:0:0/96',  // IPv4-mapped
        '::/96',          // IPv4-compatible, including the unspecified address
        '100::/64',       // discard-only
        '2002::/16',      // 6to4
        '2001::/32',      // Teredo tunneling
        '2001:db8::/32',  // documentation
        '2001:0002::/48', // benchmarking
        '64:ff9b::/96',   // NAT64 well-known prefix
        '64:ff9b:1::/48', // NAT64 local-use prefix
        'ff00::/8',       // multicast
    ];

    /**
     * Checks whether an IP address is valid.
     *
     * @internal
     */
    public static function checkIp(string $ip, mixed $version): bool
    {
        $flag = match ($version) {
            Ip::V4, Ip::V4_NO_PUBLIC, Ip::V4_ONLY_PRIVATE, Ip::V4_ONLY_RESERVED => \FILTER_FLAG_IPV4,
            Ip::V6, Ip::V6_NO_PUBLIC, Ip::V6_ONLY_PRIVATE, Ip::V6_ONLY_RESERVED => \FILTER_FLAG_IPV6,
            Ip::V4_NO_PRIVATE => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::V6_NO_PRIVATE => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::ALL_NO_PRIVATE => \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::V4_NO_RESERVED => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_RES_RANGE,
            Ip::V6_NO_RESERVED => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_RES_RANGE,
            Ip::ALL_NO_RESERVED => \FILTER_FLAG_NO_RES_RANGE,
            Ip::V4_ONLY_PUBLIC => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            Ip::V6_ONLY_PUBLIC => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            Ip::ALL_ONLY_PUBLIC => \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            default => 0,
        };

        if (!filter_var($ip, \FILTER_VALIDATE_IP, $flag)) {
            return false;
        }

        if ($flag & (\FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) && self::isNonPublicIp($ip)) {
            return false;
        }

        $inverseFlag = match ($version) {
            Ip::V4_NO_PUBLIC, Ip::V6_NO_PUBLIC, Ip::ALL_NO_PUBLIC => \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            Ip::V4_ONLY_PRIVATE, Ip::V6_ONLY_PRIVATE, Ip::ALL_ONLY_PRIVATE => \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::V4_ONLY_RESERVED, Ip::V6_ONLY_RESERVED, Ip::ALL_ONLY_RESERVED => \FILTER_FLAG_NO_RES_RANGE,
            default => 0,
        };

        if ($inverseFlag && filter_var($ip, \FILTER_VALIDATE_IP, $inverseFlag)) {
            return false;
        }

        return true;
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Ip) {
            throw new UnexpectedTypeException($constraint, Ip::class);
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

        if (!self::checkIp($value, $constraint->version)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Ip::INVALID_IP_ERROR)
                ->addViolation();
        }
    }

    /**
     * Tells whether an address belongs to one of the non-public subnets, whatever its textual spelling.
     */
    private static function isNonPublicIp(string $ip): bool
    {
        if (false === $packed = @inet_pton($ip)) {
            // filter_var() parses IPv6 addresses on its own, while inet_pton() rejects all of them
            // when PHP is compiled with "--disable-ipv6". The address cannot be classified, so the
            // only safe answer for these security-purposed modes is to consider it non-public.
            return true;
        }

        $subnets = 4 === \strlen($packed) ? self::NON_PUBLIC_V4_SUBNETS : self::NON_PUBLIC_V6_SUBNETS;

        foreach ($subnets as $subnet) {
            [$address, $bits] = explode('/', $subnet);
            $bytes = (int) $bits >> 3;
            $extraBits = (int) $bits & 7;

            if (false === $prefix = inet_pton($address)) {
                // Unreachable in practice, parsing $ip above already proved this family is
                // supported, but a subnet that cannot be parsed must not silently be skipped.
                return true;
            }

            if (substr($packed, 0, $bytes) !== substr($prefix, 0, $bytes)) {
                continue;
            }

            if (!$extraBits) {
                return true;
            }

            $mask = 0xFF << (8 - $extraBits) & 0xFF;

            if ((\ord($packed[$bytes]) & $mask) === (\ord($prefix[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
