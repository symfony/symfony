<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DnsMock
{
    /**
     * @var array
     */
    private static $hosts = array();

    /**
     * @var array
     */
    private static $dnsTypes = array(
        'A' => DNS_A,
        'MX' => DNS_MX,
        'NS' => DNS_NS,
        'SOA' => DNS_SOA,
        'PTR' => DNS_PTR,
        'CNAME' => DNS_CNAME,
        'AAAA' => DNS_AAAA,
        'A6' => DNS_A6,
        'SRV' => DNS_SRV,
        'NAPTR' => DNS_NAPTR,
        'TXT' => DNS_TXT,
        'HINFO' => DNS_HINFO,
    );

    /**
     * Configures the mock values for DNS queries.
     *
     * @param array $hosts Mocked hosts as keys, arrays of DNS records as returned by dns_get_record() as values
     */
    public static function withMockedHosts(array $hosts)
    {
        self::$hosts = $hosts;
    }

    /**
     * Searches DNS for records of type type corresponding to host.
     *
     * @param string $hostname May either be the IP address in dotted-quad notation or the host name.
     * @param string $type     May be any one of: A, MX, NS, SOA, PTR, CNAME, AAAA, A6, SRV, NAPTR, TXT or ANY.
     *
     * @return bool
     */
    public static function checkdnsrr($hostname, $type = 'MX')
    {
        if (!self::$hosts) {
            return \checkdnsrr($hostname, $type);
        }
        if (isset(self::$hosts[$hostname])) {
            $type = strtoupper($type);

            foreach (self::$hosts[$hostname] as $record) {
                if ($record['type'] === $type) {
                    return true;
                }
                if ('ANY' === $type && isset(self::$dnsTypes[$record['type']]) && 'HINFO' !== $record['type']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Searches DNS for MX records corresponding to hostname.
     *
     * @param string     $hostname The Internet host name.
     * @param array      $mxhosts  List of the MX records found is placed into the array mxhosts.
     * @param array|null $weight   If the weight array is given, it will be filled with the weight information gathered.
     *
     * @return bool
     */
    public static function getmxrr($hostname, &$mxhosts, &$weight = null)
    {
        if (!self::$hosts) {
            return \getmxrr($hostname, $mxhosts, $weight);
        }
        $mxhosts = $weight = array();

        if (isset(self::$hosts[$hostname])) {
            foreach (self::$hosts[$hostname] as $record) {
                if ('MX' === $record['type']) {
                    $mxhosts[] = $record['host'];
                    $weight[] = $record['pri'];
                }
            }
        }

        return (bool) $mxhosts;
    }

    /**
     * Returns the host name of the Internet host specified by ip_address.
     *
     * @param string $ipAddress The host IP address.
     *
     * @return int|string
     */
    public static function gethostbyaddr($ipAddress)
    {
        if (!self::$hosts) {
            return \gethostbyaddr($ipAddress);
        }
        foreach (self::$hosts as $hostname => $records) {
            foreach ($records as $record) {
                if ('A' === $record['type'] && $ipAddress === $record['ip']) {
                    return $hostname;
                }
                if ('AAAA' === $record['type'] && $ipAddress === $record['ipv6']) {
                    return $hostname;
                }
            }
        }

        return $ipAddress;
    }

    /**
     * Returns the IPv4 address of the Internet host specified by hostname.
     *
     * @param string $hostname The host name.
     *
     * @return string
     */
    public static function gethostbyname($hostname)
    {
        if (!self::$hosts) {
            return \gethostbyname($hostname);
        }
        if (isset(self::$hosts[$hostname])) {
            foreach (self::$hosts[$hostname] as $record) {
                if ('A' === $record['type']) {
                    return $record['ip'];
                }
            }
        }

        return $hostname;
    }

    /**
     * Returns a list of IPv4 addresses to which the Internet host specified by hostname resolves.
     *
     * @param string $hostname The host name.
     *
     * @return array|bool
     */
    public static function gethostbynamel($hostname)
    {
        if (!self::$hosts) {
            return \gethostbynamel($hostname);
        }
        $ips = false;

        if (isset(self::$hosts[$hostname])) {
            $ips = array();

            foreach (self::$hosts[$hostname] as $record) {
                if ('A' === $record['type']) {
                    $ips[] = $record['ip'];
                }
            }
        }

        return $ips;
    }

    /**
     * Fetch DNS Resource Records associated with the given hostname.
     *
     * @param string     $hostname The host name.
     * @param int        $type     May be any one of the following: DNS_A, DNS_CNAME, DNS_HINFO, DNS_MX, DNS_NS, DNS_PTR,
     *                             DNS_SOA, DNS_TXT, DNS_AAAA, DNS_SRV, DNS_NAPTR, DNS_A6, DNS_ALL or DNS_ANY.
     * @param array|null $authns   The Resource Records for the Authoritative Name Servers.
     * @param array|null $addtl    Any Additional Records.
     * @param bool       $raw      Query only the requestd type.
     *
     * @return array|bool
     */
    public static function dns_get_record($hostname, $type = DNS_ANY, &$authns = null, &$addtl = null, $raw = false)
    {
        if (!self::$hosts) {
            return \dns_get_record($hostname, $type, $authns, $addtl, $raw);
        }

        $records = false;

        if (isset(self::$hosts[$hostname])) {
            if (DNS_ANY === $type) {
                $type = DNS_ALL;
            }
            $records = array();

            foreach (self::$hosts[$hostname] as $record) {
                if (isset(self::$dnsTypes[$record['type']]) && (self::$dnsTypes[$record['type']] & $type)) {
                    $records[] = array_merge(
                        array('host' => $hostname, 'class' => 'IN', 'ttl' => 1, 'type' => $record['type']),
                        $record
                    );
                }
            }
        }

        return $records;
    }

    /**
     * Registers and configures the dns mock handler.
     *
     * @param string $class
     */
    public static function register($class)
    {
        $self = get_called_class();

        $mockedNs = array(substr($class, 0, strrpos($class, '\\')));
        if (0 < strpos($class, '\\Tests\\')) {
            $ns = str_replace('\\Tests\\', '\\', $class);
            $mockedNs[] = substr($ns, 0, strrpos($ns, '\\'));
        } elseif (0 === strpos($class, 'Tests\\')) {
            $mockedNs[] = substr($class, 6, strrpos($class, '\\') - 6);
        }
        foreach ($mockedNs as $ns) {
            if (function_exists($ns.'\checkdnsrr')) {
                continue;
            }
            eval(<<<EOPHP
namespace $ns;

function checkdnsrr(\$host, \$type = 'MX')
{
    return \\$self::checkdnsrr(\$host, \$type);
}

function dns_check_record(\$host, \$type = 'MX')
{
    return \\$self::checkdnsrr(\$host, \$type);
}

function getmxrr(\$hostname, &\$mxhosts, &\$weight = null)
{
    return \\$self::getmxrr(\$hostname, \$mxhosts, \$weight);
}

function dns_get_mx(\$hostname, &\$mxhosts, &\$weight = null)
{
    return \\$self::getmxrr(\$hostname, \$mxhosts, \$weight);
}

function gethostbyaddr(\$ipAddress)
{
    return \\$self::gethostbyaddr(\$ipAddress);
}

function gethostbyname(\$hostname)
{
    return \\$self::gethostbyname(\$hostname);
}

function gethostbynamel(\$hostname)
{
    return \\$self::gethostbynamel(\$hostname);
}

function dns_get_record(\$hostname, \$type = DNS_ANY, &\$authns = null, &\$addtl = null, \$raw = false)
{
    return \\$self::dns_get_record(\$hostname, \$type, \$authns, \$addtl, \$raw);
}

EOPHP
            );
        }
    }
}
