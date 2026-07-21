<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Http utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IpUtils
{
    public const PRIVATE_SUBNETS = [
        '127.0.0.0/8',    // RFC1700 (Loopback)
        '10.0.0.0/8',     // RFC1918
        '192.168.0.0/16', // RFC1918
        '192.0.2.0/24',   // Documentation Ranges TEST-NET-1 (RFC 5737)
        '198.51.100.0/24', // Documentation Ranges TEST-NET-2 (RFC 5737)
        '203.0.113.0/24', // Documentation Ranges TEST-NET-3 (RFC 5737)
        '172.16.0.0/12',  // RFC1918
        '169.254.0.0/16', // RFC3927
        '198.18.0.0/15',  // IPv4 Benchmarking (RFC 2544)
        '0.0.0.0/8',      // RFC5735
        '240.0.0.0/4',    // RFC1112
        '100.64.0.0/10',  // RFC6598
        '::1/128',        // Loopback
        'fc00::/7',       // Unique Local Address
        'fe80::/10',      // Link Local Address
        '::ffff:0:0/96',  // IPv4-mapped IPv6 addresses (RFC 4291 section 2.5.5.2)
        '::/128',         // Unspecified address
        '::/96',          // IPv4-compatible IPv6 addresses (RFC 4291 section 2.5.5.1)
        '2002::/16',      // 6to4 (RFC 3056)
        '2001::/32',      // Teredo tunneling (RFC 4380)
        '2001:db8::/32',  // Documentation Ranges (RFC 3849)
        '2001:0002::/48', // IPv6 Benchmarking (RFC 5180 and corrections)
        '64:ff9b::/96',   // NAT64 well-known prefix (RFC 6052)
        '64:ff9b:1::/48', // NAT64 local-use prefix (RFC 8215)
    ];

    private const IPV4_MAX = 0xFFFFFFFF;
    private const IPV4_MAX_PART = 0xFF;
    private const IPV4_MAX_TWO_PARTS_SUFFIX = 0xFFFFFF;
    private const IPV4_MAX_THREE_PARTS_SUFFIX = 0xFFFF;
    private const IPV4_PART_COUNT = 4;
    private const IPV4_FIRST_PART_SHIFT = 24;
    private const IPV4_SECOND_PART_SHIFT = 16;
    private const IPV4_THIRD_PART_SHIFT = 8;
    private const IPV4_DECIMAL_BASE = 10;
    private const IPV4_OCTAL_BASE = 8;
    private const IPV4_HEXADECIMAL_BASE = 16;
    private const ASCII_ZERO = 48;
    private const ASCII_NINE = 57;
    private const ASCII_UPPER_A = 65;
    private const ASCII_UPPER_F = 70;
    private const ASCII_LOWER_A = 97;
    private const ASCII_LOWER_F = 102;

    private static array $checkedIps = [];

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of given IPs or subnets.
     *
     * @param string|array $ips List of IPs or subnets (can be a string if only a single one)
     */
    public static function checkIp(string $requestIp, string|array $ips): bool
    {
        if (!\is_array($ips)) {
            $ips = [$ips];
        }

        $method = substr_count($requestIp, ':') > 1 ? 'checkIp6' : 'checkIp4';

        foreach ($ips as $ip) {
            if (self::$method($requestIp, $ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compares two IPv4 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @param string $ip IPv4 address or subnet in CIDR notation
     *
     * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
     */
    public static function checkIp4(string $requestIp, string $ip): bool
    {
        $cacheKey = $requestIp.'-'.$ip.'-v4';
        if (null !== $cacheValue = self::getCacheResult($cacheKey)) {
            return $cacheValue;
        }

        if (!filter_var($requestIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            if (null === $requestIp = self::normalizeIp4($requestIp)) {
                return self::setCacheResult($cacheKey, false);
            }
        }

        if (!filter_var($requestIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return self::setCacheResult($cacheKey, false);
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if ('0' === $netmask) {
                return self::setCacheResult($cacheKey, false !== filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4));
            }

            if ($netmask < 0 || $netmask > 32) {
                return self::setCacheResult($cacheKey, false);
            }
        } else {
            $address = $ip;
            $netmask = 32;
        }

        if (false === ip2long($address)) {
            return self::setCacheResult($cacheKey, false);
        }

        return self::setCacheResult($cacheKey, 0 === substr_compare(\sprintf('%032b', ip2long($requestIp)), \sprintf('%032b', ip2long($address)), 0, $netmask));
    }

    private static function normalizeIp4(string $requestIp): ?string
    {
        $parts = explode('.', $requestIp);

        if (self::IPV4_PART_COUNT < \count($parts)) {
            return null;
        }

        $numbers = [];
        foreach ($parts as $part) {
            if ('' === $part || null === $number = self::parseIp4Part($part)) {
                return null;
            }

            $numbers[] = $number;
        }

        $long = match (\count($numbers)) {
            1 => self::normalizeIp4SinglePart($numbers),
            2 => self::normalizeIp4TwoParts($numbers),
            3 => self::normalizeIp4ThreeParts($numbers),
            self::IPV4_PART_COUNT => self::normalizeIp4FourParts($numbers),
            default => null,
        };

        return null === $long ? null : long2ip($long);
    }

    private static function normalizeIp4SinglePart(array $numbers): ?int
    {
        return $numbers[0] <= self::IPV4_MAX ? $numbers[0] : null;
    }

    private static function normalizeIp4TwoParts(array $numbers): ?int
    {
        if ($numbers[0] > self::IPV4_MAX_PART || $numbers[1] > self::IPV4_MAX_TWO_PARTS_SUFFIX) {
            return null;
        }

        return ($numbers[0] << self::IPV4_FIRST_PART_SHIFT) | $numbers[1];
    }

    private static function normalizeIp4ThreeParts(array $numbers): ?int
    {
        if ($numbers[0] > self::IPV4_MAX_PART
            || $numbers[1] > self::IPV4_MAX_PART
            || $numbers[2] > self::IPV4_MAX_THREE_PARTS_SUFFIX
        ) {
            return null;
        }

        return ($numbers[0] << self::IPV4_FIRST_PART_SHIFT)
            | ($numbers[1] << self::IPV4_SECOND_PART_SHIFT)
            | $numbers[2];
    }

    private static function normalizeIp4FourParts(array $numbers): ?int
    {
        if ($numbers[0] > self::IPV4_MAX_PART
            || $numbers[1] > self::IPV4_MAX_PART
            || $numbers[2] > self::IPV4_MAX_PART
            || $numbers[3] > self::IPV4_MAX_PART
        ) {
            return null;
        }

        return ($numbers[0] << self::IPV4_FIRST_PART_SHIFT)
            | ($numbers[1] << self::IPV4_SECOND_PART_SHIFT)
            | ($numbers[2] << self::IPV4_THIRD_PART_SHIFT)
            | $numbers[3];
    }

    private static function parseIp4Part(string $part): ?int
    {
        if (str_starts_with($part, '0x') || str_starts_with($part, '0X')) {
            return self::parseIp4PartNumber(substr($part, 2), self::IPV4_HEXADECIMAL_BASE);
        }

        if (str_starts_with($part, '0') && '0' !== $part) {
            return self::parseIp4PartNumber($part, self::IPV4_OCTAL_BASE);
        }

        return self::parseIp4PartNumber($part, self::IPV4_DECIMAL_BASE);
    }

    private static function parseIp4PartNumber(string $part, int $base): ?int
    {
        if ('' === $part) {
            return null;
        }

        $number = 0;
        $length = \strlen($part);

        for ($i = 0; $i < $length; ++$i) {
            if (null === $digit = self::parseIp4Digit($part[$i])) {
                return null;
            }

            if ($digit >= $base) {
                return null;
            }

            $number = $number * $base + $digit;

            if ($number > self::IPV4_MAX) {
                return null;
            }
        }

        return $number;
    }

    private static function parseIp4Digit(string $digit): ?int
    {
        $ord = \ord($digit);

        if (self::ASCII_ZERO <= $ord && $ord <= self::ASCII_NINE) {
            return $ord - self::ASCII_ZERO;
        }

        if (self::ASCII_LOWER_A <= $ord && $ord <= self::ASCII_LOWER_F) {
            return $ord - self::ASCII_LOWER_A + self::IPV4_DECIMAL_BASE;
        }

        if (self::ASCII_UPPER_A <= $ord && $ord <= self::ASCII_UPPER_F) {
            return $ord - self::ASCII_UPPER_A + self::IPV4_DECIMAL_BASE;
        }

        return null;
    }

    /**
     * Compares two IPv6 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @author David Soria Parra <dsp at php dot net>
     *
     * @see https://github.com/dsp/v6tools
     *
     * @param string $ip IPv6 address or subnet in CIDR notation
     *
     * @throws \RuntimeException When IPV6 support is not enabled
     */
    public static function checkIp6(string $requestIp, string $ip): bool
    {
        $cacheKey = $requestIp.'-'.$ip.'-v6';
        if (null !== $cacheValue = self::getCacheResult($cacheKey)) {
            return $cacheValue;
        }

        if (!((\extension_loaded('sockets') && \defined('AF_INET6')) || @inet_pton('::1'))) {
            throw new \RuntimeException('Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".');
        }

        // Check to see if we were given a IP4 $requestIp or $ip by mistake
        if (!filter_var($requestIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return self::setCacheResult($cacheKey, false);
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if (!filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                return self::setCacheResult($cacheKey, false);
            }

            if ('0' === $netmask) {
                return (bool) unpack('n*', @inet_pton($address));
            }

            if ($netmask < 1 || $netmask > 128) {
                return self::setCacheResult($cacheKey, false);
            }
        } else {
            if (!filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                return self::setCacheResult($cacheKey, false);
            }

            $address = $ip;
            $netmask = 128;
        }

        $bytesAddr = unpack('n*', @inet_pton($address));
        $bytesTest = unpack('n*', @inet_pton($requestIp));

        if (!$bytesAddr || !$bytesTest) {
            return self::setCacheResult($cacheKey, false);
        }

        for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; ++$i) {
            $left = $netmask - 16 * ($i - 1);
            $left = ($left <= 16) ? $left : 16;
            $mask = ~(0xFFFF >> $left) & 0xFFFF;
            if (($bytesAddr[$i] & $mask) != ($bytesTest[$i] & $mask)) {
                return self::setCacheResult($cacheKey, false);
            }
        }

        return self::setCacheResult($cacheKey, true);
    }

    /**
     * Anonymizes an IP/IPv6.
     *
     * Removes the last byte for v4 and the last 8 bytes for v6 IPs
     */
    public static function anonymize(string $ip): string
    {
        /*
         * If the IP contains a % symbol, then it is a local-link address with scoping according to RFC 4007
         * In that case, we only care about the part before the % symbol, as the following functions, can only work with
         * the IP address itself. As the scope can leak information (containing interface name), we do not want to
         * include it in our anonymized IP data.
         */
        if (str_contains($ip, '%')) {
            $ip = substr($ip, 0, strpos($ip, '%'));
        }

        $wrappedIPv6 = false;
        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $wrappedIPv6 = true;
            $ip = substr($ip, 1, -1);
        }

        $packedAddress = inet_pton($ip);
        if (4 === \strlen($packedAddress)) {
            $mask = '255.255.255.0';
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff:ffff'))) {
            $mask = '::ffff:ffff:ff00';
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff'))) {
            $mask = '::ffff:ff00';
        } else {
            $mask = 'ffff:ffff:ffff:ffff:0000:0000:0000:0000';
        }
        $ip = inet_ntop($packedAddress & inet_pton($mask));

        if ($wrappedIPv6) {
            $ip = '['.$ip.']';
        }

        return $ip;
    }

    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of private IP subnets.
     */
    public static function isPrivateIp(string $requestIp): bool
    {
        return self::checkIp($requestIp, self::PRIVATE_SUBNETS);
    }

    private static function getCacheResult(string $cacheKey): ?bool
    {
        if (isset(self::$checkedIps[$cacheKey])) {
            // Move the item last in cache (LRU)
            $value = self::$checkedIps[$cacheKey];
            unset(self::$checkedIps[$cacheKey]);
            self::$checkedIps[$cacheKey] = $value;

            return self::$checkedIps[$cacheKey];
        }

        return null;
    }

    private static function setCacheResult(string $cacheKey, bool $result): bool
    {
        if (1000 < \count(self::$checkedIps)) {
            // stop memory leak if there are many keys
            self::$checkedIps = \array_slice(self::$checkedIps, 500, null, true);
        }

        return self::$checkedIps[$cacheKey] = $result;
    }
}
