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

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\InterceptedHttpClient;
use Amp\Http\Client\Interceptor\RetryRequests;
use Amp\Http\Client\PooledHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Tunnel\Http1TunnelConnector;
use Amp\Http\Tunnel\Https1TunnelConnector;
use Amp\Promise;
use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\DnsConnector;
use Amp\Socket\SocketAddress;
use Amp\Success;
use Psr\Log\LoggerInterface;

/**
 * Internal representation of the Amp client's state.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class AmpClientState extends ClientState
{
    public $dnsCache = [];
    public $responseCount = 0;
    public $pushedResponses = [];

    private $clients = [];
    private $clientConfigurator;
    private $maxHostConnections;
    private $maxPendingPushes;
    private $logger;

    public function __construct(?callable $clientConfigurator, int $maxHostConnections, int $maxPendingPushes, ?LoggerInterface &$logger)
    {
        $this->clientConfigurator = $clientConfigurator ?? static function (PooledHttpClient $client) {
            return new InterceptedHttpClient($client, new RetryRequests(2));
        };
        $this->maxHostConnections = $maxHostConnections;
        $this->maxPendingPushes = $maxPendingPushes;
        $this->logger = &$logger;
    }

    /**
     * @return Promise<Response>
     */
    public function request(array $options, Request $request, CancellationToken $cancellation, array &$info, \Closure $onProgress, &$handle): Promise
    {
        if ($options['proxy']) {
            if ($request->hasHeader('proxy-authorization')) {
                $options['proxy']['auth'] = $request->getHeader('proxy-authorization');
            }

            // Matching "no_proxy" should follow the behavior of curl
            $host = $request->getUri()->getHost();
            foreach ($options['proxy']['no_proxy'] as $rule) {
                $dotRule = '.'.ltrim($rule, '.');

                if ('*' === $rule || $host === $rule || substr($host, -\strlen($dotRule)) === $dotRule) {
                    $options['proxy'] = null;
                    break;
                }
            }
        }

        $request = clone $request;

        if ($request->hasHeader('proxy-authorization')) {
            $request->removeHeader('proxy-authorization');
        }

        if ($options['capture_peer_cert_chain']) {
            $info['peer_certificate_chain'] = [];
        }

        $request->addEventListener(new AmpListener($info, $options['peer_fingerprint']['pin-sha256'] ?? [], $onProgress, $handle));
        $request->setPushHandler(function ($request, $response) use ($options): Promise {
            return $this->handlePush($request, $response, $options);
        });

        ($request->hasHeader('content-length') ? new Success((int) $request->getHeader('content-length')) : $request->getBody()->getBodyLength())
            ->onResolve(static function ($e, $bodySize) use (&$info) {
                if (null !== $bodySize && 0 <= $bodySize) {
                    $info['upload_content_length'] = ((1 + $info['upload_content_length']) ?? 1) - 1 + $bodySize;
                }
            });

        [$client, $connector] = $this->getClient($options);
        $response = $client->request($request, $cancellation);
        $response->onResolve(static function ($e) use ($connector, &$handle) {
            if (null === $e) {
                $handle = $connector->handle;
            }
        });

        return $response;
    }

    private function getClient(array $options): array
    {
        $options = [
            'bindto' => $options['bindto'] ?: '0',
            'verify_peer' => $options['verify_peer'],
            'capath' => $options['capath'],
            'cafile' => $options['cafile'],
            'local_cert' => $options['local_cert'],
            'local_pk' => $options['local_pk'],
            'ciphers' => $options['ciphers'],
            'capture_peer_cert_chain' => $options['capture_peer_cert_chain'] || $options['peer_fingerprint'],
            'proxy' => $options['proxy'],
        ];

        $key = md5(serialize($options));

        if (isset($this->clients[$key])) {
            return $this->clients[$key];
        }

        $context = new ClientTlsContext('');
        $options['verify_peer'] || $context = $context->withoutPeerVerification();
        $options['cafile'] && $context = $context->withCaFile($options['cafile']);
        $options['capath'] && $context = $context->withCaPath($options['capath']);
        $options['local_cert'] && $context = $context->withCertificate(new Certificate($options['local_cert'], $options['local_pk']));
        $options['ciphers'] && $context = $context->withCiphers($options['ciphers']);
        $options['capture_peer_cert_chain'] && $context = $context->withPeerCapturing();

        $connector = $handleConnector = new class() implements Connector {
            public $connector;
            public $uri;
            public $handle;

            public function connect(string $uri, ?ConnectContext $context = null, ?CancellationToken $token = null): Promise
            {
                $result = $this->connector->connect($this->uri ?? $uri, $context, $token);
                $result->onResolve(function ($e, $socket) {
                    $this->handle = null !== $socket ? $socket->getResource() : false;
                });

                return $result;
            }
        };
        $connector->connector = new DnsConnector(new AmpResolver($this->dnsCache));

        $context = (new ConnectContext())
            ->withTcpNoDelay()
            ->withTlsContext($context);

        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                $connector->uri = 'unix://'.$options['bindto'];
            } else {
                $context = $context->withBindTo($options['bindto']);
            }
        }

        if ($options['proxy']) {
            $proxyUrl = parse_url($options['proxy']['url']);
            $proxySocket = new SocketAddress($proxyUrl['host'], $proxyUrl['port']);
            $proxyHeaders = $options['proxy']['auth'] ? ['Proxy-Authorization' => $options['proxy']['auth']] : [];

            if ('ssl' === $proxyUrl['scheme']) {
                $connector = new Https1TunnelConnector($proxySocket, $context->getTlsContext(), $proxyHeaders, $connector);
            } else {
                $connector = new Http1TunnelConnector($proxySocket, $proxyHeaders, $connector);
            }
        }

        $maxHostConnections = 0 < $this->maxHostConnections ? $this->maxHostConnections : \PHP_INT_MAX;
        $pool = new DefaultConnectionFactory($connector, $context);
        $pool = ConnectionLimitingPool::byAuthority($maxHostConnections, $pool);

        return $this->clients[$key] = [($this->clientConfigurator)(new PooledHttpClient($pool)), $handleConnector];
    }

    private function handlePush(Request $request, Promise $response, array $options): Promise
    {
        $deferred = new Deferred();
        $authority = $request->getUri()->getAuthority();

        if ($this->maxPendingPushes <= \count($this->pushedResponses[$authority] ?? [])) {
            $fifoUrl = key($this->pushedResponses[$authority]);
            unset($this->pushedResponses[$authority][$fifoUrl]);
            $this->logger && $this->logger->debug(sprintf('Evicting oldest pushed response: "%s"', $fifoUrl));
        }

        $url = (string) $request->getUri();
        $this->logger && $this->logger->debug(sprintf('Queueing pushed response: "%s"', $url));
        $this->pushedResponses[$authority][] = [$url, $deferred, $request, $response, [
            'proxy' => $options['proxy'],
            'bindto' => $options['bindto'],
            'local_cert' => $options['local_cert'],
            'local_pk' => $options['local_pk'],
        ]];

        return $deferred->promise();
    }
}
