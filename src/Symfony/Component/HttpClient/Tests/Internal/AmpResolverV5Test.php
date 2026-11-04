<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Internal;

use Amp\Dns\DnsRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Internal\AmpResolverV5;

class AmpResolverV5Test extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DnsRecord::class)) {
            $this->markTestSkipped('amphp/dns v2 is required.');
        }
    }

    public function testQueryReturnsTheResolvedAddresses()
    {
        $dnsMap = ['symfony.invalid' => '127.0.0.1', 'ipv6.symfony.invalid' => '::1'];
        $resolver = new AmpResolverV5($dnsMap);

        $records = $resolver->query('symfony.invalid', DnsRecord::A);
        $this->assertCount(1, $records);
        $this->assertSame('127.0.0.1', $records[0]->getValue());

        $records = $resolver->query('ipv6.symfony.invalid', DnsRecord::AAAA);
        $this->assertCount(1, $records);
        $this->assertSame('::1', $records[0]->getValue());
    }
}
