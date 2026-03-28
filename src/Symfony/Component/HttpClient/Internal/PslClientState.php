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

use Psl\Async;
use Psl\DateTime\Duration;
use Psl\DNS;
use Psl\HTTP\Client as PslClient;
use Psl\HTTP\Client\Connection\ConnectionMetadata;
use Psl\HTTP\Message;
use Psl\TCP;
use Psl\TLS;
use Psl\URL;
use Psr\Log\LoggerInterface;

/**
 * Internal representation of the PSL client's state.
 *
 * @author Seifeddine Gmati <azjezz@carthage.software>
 *
 * @internal
 */
final class PslClientState extends ClientState
{
    /** @var array<string, string> */
    public array $dnsCache = [];
    public int $responseCount = 0;

    private DNS\BlockingSystemResolver $systemResolver;

    /** @var array<string, array{PslClient\Connection\PooledConnector, PslClient\ClientConfiguration, PslClient\SendConfiguration}> */
    private array $clients = [];

    public function __construct(
        private ?LoggerInterface &$logger,
    ) {
        $this->systemResolver = new DNS\BlockingSystemResolver();
    }

    /**
     * @param array<string, mixed> $options Symfony-prepared request options
     */
    public function request(
        array $options,
        Message\Request $request,
        Async\CancellationTokenInterface $cancellation,
        array &$info,
        \Closure $onProgress,
        ?\Closure $onInformationalResponse = null,
    ): Message\Transaction {
        $info['start_time'] ??= microtime(true);

        if ($options['proxy']) {
            if ($request->headers->has('proxy-authorization')) {
                $options['proxy']['auth'] = $request->headers->get('proxy-authorization');
            }

            // Matching "no_proxy" should follow the behavior of curl
            $host = $request->url?->authority->host->toString() ?? '';
            foreach ($options['proxy']['no_proxy'] as $rule) {
                $dotRule = '.'.ltrim($rule, '.');

                if ('*' === $rule || $host === $rule || str_ends_with($host, $dotRule)) {
                    $options['proxy'] = null;
                    break;
                }
            }
        }

        if ($request->headers->has('proxy-authorization')) {
            $request = $request->withoutHeader('proxy-authorization');
        }

        [$pooledConnector, $clientConfig, $sendConfig] = $this->getClient($options);

        $isHttps = null !== $request->url && 'https' === $request->url->scheme;
        if ($isHttps && !$options['http_version']) {
            $sendConfig = new PslClient\SendConfiguration(
                tlsConfiguration: $sendConfig->tlsConfiguration,
                protocolVersions: [Message\ProtocolVersion::V20, Message\ProtocolVersion::V11],
            );
        }

        $connectionTimeout = null;
        if ($options['max_connect_duration'] > 0) {
            $connectionTimeout = Duration::nanoseconds((int) ($options['max_connect_duration'] * 1e9));
        }

        $sendConfig = new PslClient\SendConfiguration(
            maxResponseHeaderSize: $sendConfig->maxResponseHeaderSize,
            maxResponseBodySize: $sendConfig->maxResponseBodySize,
            baseUrl: $sendConfig->baseUrl,
            tlsConfiguration: $sendConfig->tlsConfiguration,
            protocolVersions: $sendConfig->protocolVersions,
            proxyConfiguration: $sendConfig->proxyConfiguration,
            onInformationalResponse: $onInformationalResponse,
            onConnection: static function (ConnectionMetadata $metadata) use (&$info, $onProgress): void {
                $info['primary_ip'] = $metadata->peerAddress->host;
                $info['primary_port'] = $metadata->peerAddress->port ?? 0;
                $info['local_ip'] = $metadata->localAddress->host;
                $info['local_port'] = $metadata->localAddress->port ?? 0;
                $info['connect_time'] = microtime(true) - $info['start_time'];
                $info['pretransfer_time'] = $info['connect_time'];
                $onProgress();
            },
            connectionTimeout: $connectionTimeout,
        );

        $resolver = new PslResolver($this->dnsCache, $this->systemResolver);
        $connector = new DNS\HTTP\Connector($pooledConnector, $resolver);
        $client = new PslClient\Client(connector: $connector, configuration: $clientConfig);

        $transaction = $client->send($request, $sendConfig, $cancellation);

        $info['starttransfer_time'] = microtime(true) - $info['start_time'];

        return $transaction;
    }

    /**
     * @return array{PslClient\Connection\PooledConnector, PslClient\ClientConfiguration, PslClient\SendConfiguration}
     */
    private function getClient(array $options): array
    {
        $cacheKey = [
            'bindto' => $options['bindto'] ?: '0',
            'verify_peer' => $options['verify_peer'],
            'capath' => $options['capath'],
            'cafile' => $options['cafile'],
            'local_cert' => $options['local_cert'],
            'local_pk' => $options['local_pk'],
            'ciphers' => $options['ciphers'],
            'proxy' => $options['proxy'],
            'crypto_method' => $options['crypto_method'],
        ];

        $key = hash('xxh128', serialize($cacheKey));

        if (isset($this->clients[$key])) {
            return $this->clients[$key];
        }

        $tlsConfig = new TLS\ClientConfiguration();

        if (!$options['verify_peer']) {
            $tlsConfig = $tlsConfig->withPeerVerification(false);
        }
        if ($options['cafile']) {
            $tlsConfig = $tlsConfig->withCertificateAuthority($options['cafile']);
        }
        if ($options['capath']) {
            $tlsConfig = $tlsConfig->withCertificateAuthorityPath($options['capath']);
        }
        if ($options['local_cert']) {
            $tlsConfig = $tlsConfig->withCertificate(new TLS\Certificate(
                certificateFile: $options['local_cert'],
                keyFile: $options['local_pk'] ?? $options['local_cert'],
                passphrase: $options['passphrase'] ?? null,
            ));
        }
        if ($options['ciphers']) {
            $tlsConfig = $tlsConfig->withCiphers($options['ciphers']);
        }
        if ($options['peer_fingerprint'] && isset($options['peer_fingerprint']['pin-sha256'])) {
            $pins = (array) $options['peer_fingerprint']['pin-sha256'];
            $hexPins = [];
            foreach ($pins as $pin) {
                $decoded = base64_decode($pin, true);
                if (false !== $decoded) {
                    $hexPins['sha256'] = bin2hex($decoded);
                }
            }
            if ($hexPins) {
                $tlsConfig = $tlsConfig->withPeerFingerprints($hexPins);
            }
        }

        $protocolVersions = [Message\ProtocolVersion::V11];
        if ($options['http_version']) {
            $protocolVersions = match ((float) $options['http_version']) {
                1.0 => [Message\ProtocolVersion::V10],
                1.1 => [Message\ProtocolVersion::V11],
                default => [Message\ProtocolVersion::V20, Message\ProtocolVersion::V11],
            };
        }

        $unixSocket = null;
        $bindTo = null;
        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                $unixSocket = $options['bindto'];
            } else {
                $bindTo = $options['bindto'];
            }
        }

        $proxyConfig = null;
        if ($options['proxy']) {
            $proxyUrl = str_replace(['tcp://', 'ssl://'], ['http://', 'https://'], $options['proxy']['url']);

            $proxyConfig = new PslClient\ProxyConfiguration(
                url: URL\parse($proxyUrl),
                authorization: $options['proxy']['auth'] ?? null,
                skipProxyFor: $options['proxy']['no_proxy'] ?? [],
            );
        }

        $clientConfig = new PslClient\ClientConfiguration(
            tlsConfiguration: $tlsConfig,
            protocolVersions: $protocolVersions,
            unixSocket: $unixSocket,
            proxyConfiguration: $proxyConfig,
        );

        $sendConfig = new PslClient\SendConfiguration(
            tlsConfiguration: $tlsConfig,
            protocolVersions: $protocolVersions,
        );

        $tcpConfig = new TCP\ConnectConfiguration(noDelay: true, bindTo: $bindTo);
        $connector = new PslClient\Connection\PooledConnector(
            tcpConnector: new TCP\Connector($tcpConfig),
        );

        return $this->clients[$key] = [$connector, $clientConfig, $sendConfig];
    }
}
