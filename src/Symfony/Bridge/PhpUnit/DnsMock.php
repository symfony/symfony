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
    private static $hosts = array();
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
                    $records[] = array_merge(array('host' => $hostname, 'class' => 'IN', 'ttl' => 1, 'type' => $record['type']), $record);
                }
            }
        }

        return $records;
    }

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
