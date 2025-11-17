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
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver;

/**
 * Handles local overrides for the DNS resolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpResolver implements DnsResolver
{
    public function __construct(
        private array &$dnsMap,
    ) {
    }

    public function resolve(string $name, ?int $typeRestriction = null, ?Cancellation $cancellation = null): array
    {
        $recordType = DnsRecord::A;
        $ip = $this->dnsMap[$name] ?? null;

        if (null !== $ip && str_contains($ip, ':')) {
            $recordType = DnsRecord::AAAA;
        }

        if (null === $ip || $recordType !== ($typeRestriction ?? $recordType)) {
            return Dns\resolve($name, $typeRestriction, $cancellation);
        }

        return [new DnsRecord($ip, $recordType, null)];
    }

    public function query(string $name, int $type, ?Cancellation $cancellation = null): array
    {
        $recordType = DnsRecord::A;
        $ip = $this->dnsMap[$name] ?? null;

        if (null !== $ip && str_contains($ip, ':')) {
            $recordType = DnsRecord::AAAA;
        }

        if (null !== $ip || $recordType !== $type) {
            return Dns\resolve($name, $type, $cancellation);
        }

        return [new DnsRecord($ip, $recordType, null)];
    }
}
