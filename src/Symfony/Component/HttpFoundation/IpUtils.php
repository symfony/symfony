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
    /**
     * Curated CIDR list of IP ranges that should be classified as
     * private / internal / non-routable on the public internet.
     *
     * Covers RFC 1918 (private), RFC 3927 (link-local), RFC 5735
     * (reserved), RFC 5771 (IPv4 multicast), RFC 6598 (CGN),
     * RFC 6666 (IPv6 discard), RFC 3849 / RFC 5737 (documentation),
     * RFC 3056 (6to4), RFC 4380 (Teredo), and the IPv6 unique-local
     * / link-local / IPv4-mapped prefixes.
     */
    public const PRIVATE_SUBNETS = [
        '127.0.0.0/8',     // RFC1700 (Loopback)
        '10.0.0.0/8',      // RFC1918
        '192.168.0.0/16',  // RFC1918
        '192.0.0.0/24',    // IETF Protocol Assignments (RFC 6890)
        '192.0.2.0/24',    // Documentation Ranges TEST-NET-1 (RFC 5737)
        '198.51.100.0/24', // Documentation Ranges TEST-NET-2 (RFC 5737)
        '203.0.113.0/24',  // Documentation Ranges TEST-NET-3 (RFC 5737)
        '172.16.0.0/12',   // RFC1918
        '169.254.0.0/16',  // RFC3927
        '192.88.99.0/24',  // 6to4 Relay Anycast (RFC 7526, deprecated)
        '198.18.0.0/15',   // IPv4 Benchmarking (RFC 2544)
        '0.0.0.0/8',       // RFC5735
        '240.0.0.0/4',     // RFC1112
        '100.64.0.0/10',   // RFC6598
        '224.0.0.0/4',     // RFC5771 (IPv4 multicast)
        '::1/128',         // Loopback
        'fc00::/7',        // Unique Local Address
        'fe80::/10',       // Link Local Address
        '::ffff:0:0/96',   // IPv4-mapped IPv6 addresses (RFC 4291 section 2.5.5.2)
        '::/128',          // Unspecified address
        '::/96',           // IPv4-compatible IPv6 addresses (RFC 4291 section 2.5.5.1)
        '100::/64',        // Discard prefix (RFC 6666)
        '2002::/16',       // 6to4 (RFC 3056)
        '2001::/32',       // Teredo tunneling (RFC 4380)
        '2001:db8::/32',   // Documentation Ranges (RFC 3849)
        '2001:0002::/48',  // IPv6 Benchmarking (RFC 5180 and corrections)
        '64:ff9b::/96',    // NAT64 well-known prefix (RFC 6052)
        '64:ff9b:1::/48',  // NAT64 local-use prefix (RFC 8215)
        'ff00::/8',        // RFC4291 (IPv6 multicast)
    ];

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
     * Removes the last bytes of IPv4 and IPv6 addresses (1 byte for IPv4 and 8 bytes for IPv6 by default).
     *
     * @param int<0, 4>  $v4Bytes
     * @param int<0, 16> $v6Bytes
     */
    public static function anonymize(string $ip, int $v4Bytes = 1, int $v6Bytes = 8): string
    {
        if ($v4Bytes < 0 || $v6Bytes < 0) {
            throw new \InvalidArgumentException('Cannot anonymize less than 0 bytes.');
        }

        if ($v4Bytes > 4 || $v6Bytes > 16) {
            throw new \InvalidArgumentException('Cannot anonymize more than 4 bytes for IPv4 and 16 bytes for IPv6.');
        }

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

        $mappedIpV4MaskGenerator = static function (string $mask, int $bytesToAnonymize) {
            $mask .= str_repeat('ff', 4 - $bytesToAnonymize);
            $mask .= str_repeat('00', $bytesToAnonymize);

            return '::'.implode(':', str_split($mask, 4));
        };

        $packedAddress = inet_pton($ip);
        if (4 === \strlen($packedAddress)) {
            $mask = rtrim(str_repeat('255.', 4 - $v4Bytes).str_repeat('0.', $v4Bytes), '.');
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff:ffff'))) {
            $mask = $mappedIpV4MaskGenerator('ffff', $v4Bytes);
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff'))) {
            $mask = $mappedIpV4MaskGenerator('', $v4Bytes);
        } else {
            $mask = str_repeat('ff', 16 - $v6Bytes).str_repeat('00', $v6Bytes);
            $mask = implode(':', str_split($mask, 4));
        }
        $ip = inet_ntop($packedAddress & inet_pton($mask));

        if ($wrappedIPv6) {
            $ip = '['.$ip.']';
        }

        return $ip;
    }

    /**
     * Normalize an IP literal to its canonical dotted-quad or colon-hex form.
     *
     * Accepts every form that HTTP clients (cURL, browsers) and PHP's
     * own network stack will interpret as an IP address before connecting:
     *
     *  * canonical IPv4 dotted-quad (e.g. "127.0.0.1")
     *  * canonical IPv6 colon-hex (e.g. "::1", "fe80::1")
     *  * IPv4-mapped IPv6 (e.g. "::ffff:127.0.0.1", "::ffff:7f00:1")
     *  * decimal / hex / octal integers (e.g. "2130706433", "0x7f000001", "017700000001")
     *  * short dotted forms (e.g. "127.1" → "127.0.0.1", "192.168.1" → "192.168.0.1")
     *  * mixed dotted with octal/hex components (e.g. "0x7f.0.0.1", "0177.0.0.01")
     *  * bracketed IPv6 literals (e.g. "[::1]")
     *
     * This is an SSRF bypass defence: without normalisation, an
     * attacker can write "http://2130706433/" and the HTTP client will
     * connect to 127.0.0.1, but filter_var(..., FILTER_VALIDATE_IP) and
     * inet_pton() both reject the decimal form, so a guard that
     * relies on those alone will see the obfuscated form as a
     * "non-IP" input and let it through unchecked.
     *
     * For IPv4-mapped IPv6, the embedded IPv4 portion is returned in
     * its dotted-quad form so callers don't have to special-case
     * IPv4-only range checks against "::ffff:127.0.0.1".
     *
     * Returns null when the input is not an IP literal in any
     * recognised form — callers should treat that as a hostname and
     * resolve it via DNS, not as a malformed IP.
     *
     * Calling IpUtils::normalize($input) !== $input is a cheap way to
     * detect obfuscated IP literals for logging / threat-intel
     * purposes without blocking them. Most callers should normalize
     * and then classify; detection-only use cases can compare against
     * the original input.
     *
     * @throws \ValueError When the input looks like an IP literal but is
     *                     malformed or out of range (e.g. "256.0.0.0")
     */
    public static function normalize(string $requestIp): ?string
    {
        $stripped = trim($requestIp, '[]');

        // IPv6 (contains at least one ':').
        if (str_contains($stripped, ':')) {
            // Strip a trailing zone identifier ("%eth0", "%1", etc.) —
            // not relevant to address classification.
            $zone = strpos($stripped, '%');
            $address = false === $zone ? $stripped : substr($stripped, 0, $zone);

            $packed = @inet_pton($address);
            if (false === $packed) {
                return null;
            }

            $canonical = @inet_ntop($packed);
            if (false === $canonical) {
                return null;
            }

            // Extract the embedded IPv4 from IPv4-mapped IPv6 so the
            // canonical form is uniform with IPv4 callers.
            if (str_starts_with($canonical, '::ffff:')) {
                $v4 = substr($canonical, 7);
                if (filter_var($v4, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                    return $v4;
                }
            }

            return $canonical;
        }

        // IPv4 territory: only digits, hex letters, '.', and 'x'.
        if (!preg_match('/^[0-9a-fA-Fx.]+$/', $stripped)) {
            return null;
        }

        // No dots: a single integer. Allow octal (leading 0) and hex
        // (leading 0x) so we accept "2130706433", "0x7f000001", and
        // "017700000001".
        if (!str_contains($stripped, '.')) {
            $value = filter_var(
                $stripped,
                \FILTER_VALIDATE_INT,
                \FILTER_FLAG_ALLOW_OCTAL | \FILTER_FLAG_ALLOW_HEX,
            );

            if (false === $value) {
                return null;
            }

            $value = (int) $value;

            // Reject values above the unsigned 32-bit max. Avoid the
            // literal 0xFFFFFFFF because it becomes -1 on 32-bit PHP.
            if (\PHP_INT_SIZE >= 8) {
                if ($value > 0xFFFFFFFF) {
                    return null;
                }
            } elseif (($value & ~0xFFFFFFFF) !== 0) {
                // 32-bit: $value may be negative for high-bit-set IPs.
                // Anything with bits set above the low 32 is invalid
                // — but filter_var with ALLOW_OCTAL|ALLOW_HEX already
                // rejects values larger than PHP_INT_MAX.
                return null;
            }

            $packed = pack('N', $value & 0xFFFFFFFF);
            $canonical = @inet_ntop($packed);
            if (false === $canonical || !filter_var($canonical, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                return null;
            }

            return $canonical;
        }

        // Dotted form: 1 to 4 components. Short forms ("127.1")
        // fill missing components with zeros before the final
        // component; matches cURL's interpretation.
        $parts = explode('.', $stripped);
        if (\count($parts) > 4) {
            return null;
        }

        $values = [];
        foreach ($parts as $p) {
            $v = filter_var(
                $p,
                \FILTER_VALIDATE_INT,
                \FILTER_FLAG_ALLOW_OCTAL | \FILTER_FLAG_ALLOW_HEX,
            );
            if (false === $v) {
                return null;
            }
            $v = (int) $v;
            // Each component must fit in 8 bits.
            if (\PHP_INT_SIZE >= 8) {
                if ($v < 0 || $v > 0xFF) {
                    return null;
                }
            } elseif (($v & ~0xFF) !== 0) {
                return null;
            }

            $values[] = $v;
        }

        while (\count($values) < 4) {
            array_splice($values, \count($values) - 1, 0, [0]);
        }

        $packed = '';
        foreach ($values as $v) {
            $packed .= \chr($v);
        }

        $canonical = @inet_ntop($packed);
        if (false === $canonical || !filter_var($canonical, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return null;
        }

        return $canonical;
    }

    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of private IP subnets.
     *
     * @throws \ValueError When $requestIp is not a valid IP address
     */
    public static function isPrivateIp(string $requestIp): bool
    {
        if (!filter_var($requestIp, \FILTER_VALIDATE_IP)) {
            throw new \ValueError(\sprintf('"%s" is not a valid IP address.', $requestIp));
        }

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
