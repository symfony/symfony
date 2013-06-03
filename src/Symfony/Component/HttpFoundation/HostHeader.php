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
 * Represents a Host header.
 *
 * A host header specifies the host and port of the
 * resource being requested.
 *
 * @author Dennis Hotson <dennis.hotson@gmail.com>
 */
class HostHeader
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $port;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Returns the host name.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Returns the port
     *
     * @return string (null if not specified)
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Builds an HostHeader instance from a string.
     *
     * @param string $headerValue
     *
     * @return HostHeader
     */
    public static function fromString($headerValue)
    {
        if (preg_match(self::_pattern(), $headerValue, $matches)) {
            $port = isset($matches['port']) ? $matches['port'] : null;
            return new self($matches['host'], $port);
        } else {
            throw new \UnexpectedValueException('Invalid Host header value: '.$headerValue);
        }
    }

    private static $_pattern;

    /**
     * Builds a regex pattern to parse a HTTP Host header
     * according to RFC3986 http://tools.ietf.org/html/rfc3986.html
     *
     * @return string
     */
    private static function _pattern()
    {
        if (isset(self::$_pattern))
            return self::$_pattern;

        $h16 = '[[:xdigit:]]{1,4}';
        $h16c = "(?: $h16 : )"; // A h16 followed by a colon
        $unreserved = '[-._~[:alpha:][:digit:]]';
        $pctEncoded = '(?: % [[:xdigit]]{2} )';
        $subDelims = "[!$&'()*+,;=]";
        $decOctet = <<<EOS
            (?:
                  [0-9]
                | (?: [1-9][0-9] )
                | (?: 1 [0-9][0-9] )
                | (?: 2 [0-4][0-9] )
                | (?: 25 [0-5] )
            )
EOS;

        $regName = <<<EOS
            (?:
                  $unreserved
                | $pctEncoded
                | $subDelims
            )*
EOS;

        $ipv4Address = <<<EOS
            (?:
                $decOctet \. $decOctet \. $decOctet \. $decOctet
            )
EOS;

        $ls32 = <<<EOS
            (?:
                (?: $h16 : $h16) | $ipv4Address
            )
EOS;

        $ipv6Address = <<<EOS
            (?:
                  (?:                           $h16c{6} $ls32 )
                | (?:                        :: $h16c{5} $ls32 )
                | (?: (?:            $h16 )? :: $h16c{4} $ls32 )
                | (?: (?: $h16c{0,1} $h16 )? :: $h16c{3} $ls32 )
                | (?: (?: $h16c{0,2} $h16 )? :: $h16c{2} $ls32 )
                | (?: (?: $h16c{0,3} $h16 )? :: $h16c    $ls32 )
                | (?: (?: $h16c{0,4} $h16 )? ::          $ls32 )
                | (?: (?: $h16c{0,5} $h16 )? ::          $h16  )
                | (?: (?: $h16c{0,6} $h16 )? ::                )
            )
EOS;

        $ipvFuture = <<<EOS
            (?:
                v [[:xdigit:]]+ \. (?: $unreserved | $subDelims | : )+
            )
EOS;

        $ipLiteral = <<<EOS
            (?: \[ (?: $ipv6Address | $ipvFuture ) \] )
EOS;

        $host = "(?P<host> $ipLiteral | $ipv4Address | $regName )";
        $port = '(?: : (?P<port> [0-9]* ) )?';
        $pattern = "/ \A $host $port \Z /x";

        return self::$_pattern = $pattern;
    }
}
