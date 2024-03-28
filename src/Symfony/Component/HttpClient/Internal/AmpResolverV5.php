<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Amp\Cancellation;
use Amp\Dns;
use Amp\Dns\DnsResolver;
use Amp\Dns\DnsRecord;

/**
 * Handles local overrides for the DNS resolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpResolverV5 implements DnsResolver
{
    public function __construct(
        private array &$dnsMap,
    ) {
    }

    public function resolve(string $name, ?int $typeRestriction = null, Cancellation $cancellation = null): array
    {
        if (!isset($this->dnsMap[$name]) || !\in_array($typeRestriction, [DnsRecord::A, null], true)) {
            return Dns\resolve($name, $typeRestriction, $cancellation);
        }

        return [new DnsRecord($this->dnsMap[$name], DnsRecord::A, null)];
    }

    public function query(string $name, int $type, Cancellation $cancellation = null): array
    {
        if (!isset($this->dnsMap[$name]) || DnsRecord::A !== $type) {
            return Dns\resolve($name, $type, $cancellation);
        }

        return [new DnsRecord($this->dnsMap[$name], DnsRecord::A, null)];
    }
}
