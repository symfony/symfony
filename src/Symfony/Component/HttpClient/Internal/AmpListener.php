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

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\InternetAddress;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpListener implements EventListener
{
    private array $info;

    /**
     * @param resource|null $handle
     */
    public function __construct(
        array &$info,
        private array $pinSha256,
        private \Closure $onProgress,
        private &$handle,
    ) {
        $info += [
            'connect_time' => 0.0,
            'pretransfer_time' => 0.0,
            'starttransfer_time' => 0.0,
            'total_time' => 0.0,
            'namelookup_time' => 0.0,
            'primary_ip' => '',
            'primary_port' => 0,
        ];

        $this->info = &$info;
    }

    public function requestStart(Request $request): void
    {
        $this->info['start_time'] ??= microtime(true);
        ($this->onProgress)();
    }

    public function connectionAcquired(Request $request, Connection $connection, int $streamCount): void
    {
        $this->info['namelookup_time'] = microtime(true) - $this->info['start_time']; // see https://github.com/amphp/socket/issues/114
        $this->info['connect_time'] = microtime(true) - $this->info['start_time'];
        ($this->onProgress)();
    }

    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        $host = $stream->getRemoteAddress()->toString();
        if ($stream->getRemoteAddress() instanceof InternetAddress) {
            $host = $stream->getRemoteAddress()->getAddress();
            $this->info['primary_port'] = $stream->getRemoteAddress()->getPort();
        }

        $this->info['primary_ip'] = $host;

        if (str_contains($host, ':')) {
            $host = '['.$host.']';
        }

        $this->info['pretransfer_time'] = microtime(true) - $this->info['start_time'];
        $this->info['debug'] .= \sprintf("* Connected to %s (%s) port %d\n", $request->getUri()->getHost(), $host, $this->info['primary_port']);

        if ((isset($this->info['peer_certificate_chain']) || $this->pinSha256) && null !== $tlsInfo = $stream->getTlsInfo()) {
            foreach ($tlsInfo->getPeerCertificates() as $cert) {
                $this->info['peer_certificate_chain'][] = openssl_x509_read($cert->toPem());
            }

            if ($this->pinSha256) {
                $pin = openssl_pkey_get_public($this->info['peer_certificate_chain'][0]);
                $pin = openssl_pkey_get_details($pin)['key'];
                $pin = \array_slice(explode("\n", $pin), 1, -2);
                $pin = base64_decode(implode('', $pin));
                $pin = base64_encode(hash('sha256', $pin, true));

                if (!\in_array($pin, $this->pinSha256, true)) {
                    throw new TransportException(\sprintf('SSL public key does not match pinned public key for "%s".', $this->info['url']));
                }
            }
        }
        ($this->onProgress)();

        $uri = $request->getUri();
        $requestUri = $uri->getPath() ?: '/';

        if ('' !== $query = $uri->getQuery()) {
            $requestUri .= '?'.$query;
        }

        if ('CONNECT' === $method = $request->getMethod()) {
            $requestUri = $uri->getHost().': '.($uri->getPort() ?? ('https' === $uri->getScheme() ? 443 : 80));
        }

        $this->info['debug'] .= \sprintf("> %s %s HTTP/%s \r\n", $method, $requestUri, $request->getProtocolVersions()[0]);

        foreach ($request->getHeaderPairs() as [$name, $value]) {
            $this->info['debug'] .= $name.': '.$value."\r\n";
        }
        $this->info['debug'] .= "\r\n";
    }

    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function requestEnd(Request $request, Response $response): void
    {
        ($this->onProgress)();
    }

    public function requestFailed(Request $request, \Throwable $exception): void
    {
        $this->handle = null;
        ($this->onProgress)();
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function requestBodyStart(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        ($this->onProgress)();
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        $this->info['starttransfer_time'] = microtime(true) - $this->info['start_time'];
        ($this->onProgress)();
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        ($this->onProgress)();
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        $this->handle = null;
        ($this->onProgress)();
    }

    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void
    {
    }

    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void
    {
    }

    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void
    {
    }

    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void
    {
    }

    public function push(Request $request): void
    {
        ($this->onProgress)();
    }

    public function requestRejected(Request $request): void
    {
        $this->handle = null;
        ($this->onProgress)();
    }
}
