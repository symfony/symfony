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

use Psl\Async\CancellationTokenInterface;
use Psl\Async\NullCancellationToken;
use Psl\DateTime\Duration;
use Psl\DNS\Record\AAAARecord;
use Psl\DNS\Record\ARecord;
use Psl\DNS\Record\RecordType;
use Psl\DNS\ResolverConvenienceMethodsTrait;
use Psl\DNS\ResolverInterface;
use Psl\DNS\Response;
use Psl\DNS\ResponseCode;
use Psl\IP\Address;

/**
 * DNS resolver with local hostname-to-IP overrides.
 *
 * Checks the DNS map first. If the hostname is found, returns a synthetic
 * A or AAAA response. If not found, delegates to the fallback resolver.
 *
 * @author Seifeddine Gmati <azjezz@carthage.software>
 *
 * @internal
 */
class PslResolver implements ResolverInterface
{
    use ResolverConvenienceMethodsTrait;

    /**
     * @param array<string, string> $dnsMap Hostname-to-IP map (passed by reference so it stays in sync with PslClientState)
     */
    public function __construct(
        private array &$dnsMap,
        private ResolverInterface $fallback,
    ) {
    }

    public function query(
        string $name,
        RecordType $type,
        CancellationTokenInterface $cancellation = new NullCancellationToken(),
        array $ednsOptions = [],
    ): Response {
        $ip = $this->dnsMap[$name] ?? null;

        if (null === $ip) {
            return $this->fallback->query($name, $type, $cancellation, $ednsOptions);
        }

        $isIPv6 = str_contains($ip, ':');

        if ($isIPv6 && RecordType::AAAA !== $type) {
            return new Response(0, ResponseCode::NonExistentDomain, [], [], []);
        }

        if (!$isIPv6 && RecordType::A !== $type) {
            return new Response(0, ResponseCode::NonExistentDomain, [], [], []);
        }

        $record = $isIPv6
            ? new AAAARecord($name, Duration::hours(1), Address::parse($ip))
            : new ARecord($name, Duration::hours(1), Address::parse($ip));

        return new Response(0, ResponseCode::NoError, [$record], [], []);
    }
}
