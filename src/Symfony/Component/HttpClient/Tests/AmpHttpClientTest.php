<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use LibDNS\Records\ResourceTypes;
use LibDNS\Records\Types\DomainName;
use LibDNS\Records\Types\Types;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AmpHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 5]);
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }

    public function testResolve2()
    {
        var_dump(PHP_INT_MAX);
        var_dump(PHP_INT_SIZE);

        $this->definitions = [
            ResourceTypes::A => [ // RFC 1035
                'address' => Types::IPV4_ADDRESS,
            ],
            ResourceTypes::AAAA  => [ // RFC 3596
                'address' => Types::IPV6_ADDRESS,
            ],
            ResourceTypes::AFSDB => [ // RFC 1183
                'subtype'  => Types::SHORT,
                'hostname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::CAA => [ // RFC 6844
                'flags' => Types::DOMAIN_NAME,
                'tag'   => Types::CHARACTER_STRING,
                'value' => Types::ANYTHING,
            ],
            ResourceTypes::CERT => [ // RFC 4398
                'type'        => Types::SHORT,
                'key-tag'     => Types::SHORT,
                'algorithm'   => Types::CHAR,
                'certificate' => Types::ANYTHING,
            ],
            ResourceTypes::CNAME => [ // RFC 1035
                'cname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::DHCID => [ // RFC 4701
                'identifier-type' => Types::SHORT,
                'digest-type'     => Types::CHAR,
                'digest'          => Types::ANYTHING,
            ],
            ResourceTypes::DLV => [ // RFC 4034
                'key-tag'     => Types::SHORT,
                'algorithm'   => Types::CHAR,
                'digest-type' => Types::CHAR,
                'digest'      => Types::ANYTHING,
            ],
            ResourceTypes::DNAME => [ // RFC 4034
                'target' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::DNSKEY => [ // RFC 6672
                'flags'      => Types::SHORT,
                'protocol'   => Types::CHAR,
                'algorithm'  => Types::CHAR,
                'public-key' => Types::ANYTHING,
            ],
            ResourceTypes::DS => [ // RFC 4034
                'key-tag'     => Types::SHORT,
                'algorithm'   => Types::CHAR,
                'digest-type' => Types::CHAR,
                'digest'      => Types::ANYTHING,
            ],
            ResourceTypes::HINFO => [ // RFC 1035
                'cpu' => Types::CHARACTER_STRING,
                'os'  => Types::CHARACTER_STRING,
            ],
            ResourceTypes::ISDN => [ // RFC 1183
                'isdn-address' => Types::CHARACTER_STRING,
                'sa'           => Types::CHARACTER_STRING,
            ],
            ResourceTypes::KEY => [ // RFC 2535
                'flags'      => Types::SHORT,
                'protocol'   => Types::CHAR,
                'algorithm'  => Types::CHAR,
                'public-key' => Types::ANYTHING,
            ],
            ResourceTypes::KX => [ // RFC 2230
                'preference' => Types::SHORT,
                'exchange'   => Types::DOMAIN_NAME,
            ],
            ResourceTypes::LOC => [ // RFC 1876
                'version'              => Types::CHAR,
                'size'                 => Types::CHAR,
                'horizontal-precision' => Types::CHAR,
                'vertical-precision'   => Types::CHAR,
                'latitude'             => Types::LONG,
                'longitude'            => Types::LONG,
                'altitude'             => Types::LONG,
            ],
            ResourceTypes::MB => [ // RFC 1035
                'madname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MD => [ // RFC 1035
                'madname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MF => [ // RFC 1035
                'madname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MG => [ // RFC 1035
                'mgmname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MINFO => [ // RFC 1035
                'rmailbx' => Types::DOMAIN_NAME,
                'emailbx' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MR => [ // RFC 1035
                'newname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::MX => [ // RFC 1035
                'preference' => Types::SHORT,
                'exchange'   => Types::DOMAIN_NAME,
            ],
            ResourceTypes::NAPTR => [ // RFC 3403
                'order'       => Types::SHORT,
                'preference'  => Types::SHORT,
                'flags'       => Types::CHARACTER_STRING,
                'services'    => Types::CHARACTER_STRING,
                'regexp'      => Types::CHARACTER_STRING,
                'replacement' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::NS => [ // RFC 1035
                'nsdname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::NULL => [ // RFC 1035
                'data' => Types::ANYTHING,
            ],
            ResourceTypes::PTR => [ // RFC 1035
                'ptrdname' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::RP => [ // RFC 1183
                'mbox-dname' => Types::DOMAIN_NAME,
                'txt-dname'  => Types::DOMAIN_NAME,
            ],
            ResourceTypes::RT => [ // RFC 1183
                'preference'        => Types::SHORT,
                'intermediate-host' => Types::DOMAIN_NAME,
            ],
            ResourceTypes::SIG => [ // RFC 4034
                'type-covered'         => Types::SHORT,
                'algorithm'            => Types::CHAR,
                'labels'               => Types::CHAR,
                'original-ttl'         => Types::LONG,
                'signature-expiration' => Types::LONG,
                'signature-inception'  => Types::LONG,
                'key-tag'              => Types::SHORT,
                'signers-name'         => Types::DOMAIN_NAME,
                'signature'            => Types::ANYTHING,
            ],
            ResourceTypes::SOA => [ // RFC 1035
                'mname'      => Types::DOMAIN_NAME,
                'rname'      => Types::DOMAIN_NAME,
                'serial'     => Types::LONG,
                'refresh'    => Types::LONG,
                'retry'      => Types::LONG,
                'expire'     => Types::LONG,
                'minimum'    => Types::LONG,
            ],
            ResourceTypes::SPF => [ // RFC 4408
                'data+' => Types::CHARACTER_STRING,
            ],
            ResourceTypes::SRV => [ // RFC 2782
                'priority' => Types::SHORT,
                'weight'   => Types::SHORT,
                'port'     => Types::SHORT,
                'name'     => Types::DOMAIN_NAME | DomainName::FLAG_NO_COMPRESSION,
            ],
            ResourceTypes::TXT => [ // RFC 1035
                'txtdata+' => Types::CHARACTER_STRING,
            ],
            ResourceTypes::WKS => [ // RFC 1035
                'address'  => Types::IPV4_ADDRESS,
                'protocol' => Types::SHORT,
                'bit-map'  => Types::BITMAP,
            ],
            ResourceTypes::X25 => [ // RFC 1183
                'psdn-address' => Types::CHARACTER_STRING,
            ],
        ];
    }
}
