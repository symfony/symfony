<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\CurlClientState;
use Symfony\Component\HttpClient\Internal\PushedResponse;
use Symfony\Component\HttpClient\Response\CurlResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A performant implementation of the HttpClientInterface contracts based on the curl extension.
 *
 * This provides fully concurrent HTTP requests, with transparent
 * HTTP/2 push when a curl version that supports it is installed.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CurlHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    use HttpClientTrait;
    use LoggerAwareTrait;

    private $defaultOptions = self::OPTIONS_DEFAULTS + [
        'auth_ntlm' => null, // array|string - an array containing the username as first value, and optionally the
                             //   password as the second one; or string like username:password - enabling NTLM auth
        'extra' => [
            'curl' => [],    // A list of extra curl options indexed by their corresponding CURLOPT_*
        ],
    ];

    /**
     * An internal object to share state between the client and its responses.
     *
     * @var CurlClientState
     */
    private $multi;

    private static $curlVersion;

    /**
     * @param array $defaultOptions     Default request's options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     * @param int   $maxPendingPushes   The maximum number of pushed responses to accept in the queue
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function __construct(array $defaultOptions = [], int $maxHostConnections = 6, int $maxPendingPushes = 50)
    {
        if (!\extension_loaded('curl')) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\CurlHttpClient" as the "curl" extension is not installed.');
        }

        $this->defaultOptions['buffer'] = $this->defaultOptions['buffer'] ?? \Closure::fromCallable([__CLASS__, 'shouldBuffer']);

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }

        $this->multi = new CurlClientState();
        self::$curlVersion = self::$curlVersion ?? curl_version();

        // Don't enable HTTP/1.1 pipelining: it forces responses to be sent in order
        if (\defined('CURLPIPE_MULTIPLEX')) {
            curl_multi_setopt($this->multi->handle, \CURLMOPT_PIPELINING, \CURLPIPE_MULTIPLEX);
        }
        if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            $maxHostConnections = curl_multi_setopt($this->multi->handle, \CURLMOPT_MAX_HOST_CONNECTIONS, 0 < $maxHostConnections ? $maxHostConnections : \PHP_INT_MAX) ? 0 : $maxHostConnections;
        }
        if (\defined('CURLMOPT_MAXCONNECTS') && 0 < $maxHostConnections) {
            curl_multi_setopt($this->multi->handle, \CURLMOPT_MAXCONNECTS, $maxHostConnections);
        }

        // Skip configuring HTTP/2 push when it's unsupported or buggy, see https://bugs.php.net/77535
        if (0 >= $maxPendingPushes || \PHP_VERSION_ID < 70217 || (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304)) {
            return;
        }

        // HTTP/2 push crashes before curl 7.61
        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > self::$curlVersion['version_number'] || !(\CURL_VERSION_HTTP2 & self::$curlVersion['features'])) {
            return;
        }

        curl_multi_setopt($this->multi->handle, \CURLMOPT_PUSHFUNCTION, function ($parent, $pushed, array $requestHeaders) use ($maxPendingPushes) {
            return $this->handlePush($parent, $pushed, $requestHeaders, $maxPendingPushes);
        });
    }

    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     *
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);
        $scheme = $url['scheme'];
        $authority = $url['authority'];
        $host = parse_url($authority, \PHP_URL_HOST);
        $url = implode('', $url);

        if (!isset($options['normalized_headers']['user-agent'])) {
            $options['headers'][] = 'User-Agent: Symfony HttpClient/Curl';
        }

        $curlopts = [
            \CURLOPT_URL => $url,
            \CURLOPT_TCP_NODELAY => true,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_MAXREDIRS => 0 < $options['max_redirects'] ? $options['max_redirects'] : 0,
            \CURLOPT_COOKIEFILE => '', // Keep track of cookies during redirects
            \CURLOPT_TIMEOUT => 0,
            \CURLOPT_PROXY => $options['proxy'],
            \CURLOPT_NOPROXY => $options['no_proxy'] ?? $_SERVER['no_proxy'] ?? $_SERVER['NO_PROXY'] ?? '',
            \CURLOPT_SSL_VERIFYPEER => $options['verify_peer'],
            \CURLOPT_SSL_VERIFYHOST => $options['verify_host'] ? 2 : 0,
            \CURLOPT_CAINFO => $options['cafile'],
            \CURLOPT_CAPATH => $options['capath'],
            \CURLOPT_SSL_CIPHER_LIST => $options['ciphers'],
            \CURLOPT_SSLCERT => $options['local_cert'],
            \CURLOPT_SSLKEY => $options['local_pk'],
            \CURLOPT_KEYPASSWD => $options['passphrase'],
            \CURLOPT_CERTINFO => $options['capture_peer_cert_chain'],
        ];

        if (1.0 === (float) $options['http_version']) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_0;
        } elseif (1.1 === (float) $options['http_version']) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
        } elseif (\defined('CURL_VERSION_HTTP2') && (\CURL_VERSION_HTTP2 & self::$curlVersion['features']) && ('https:' === $scheme || 2.0 === (float) $options['http_version'])) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_2_0;
        }

        if (isset($options['auth_ntlm'])) {
            $curlopts[\CURLOPT_HTTPAUTH] = \CURLAUTH_NTLM;
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;

            if (\is_array($options['auth_ntlm'])) {
                $count = \count($options['auth_ntlm']);
                if ($count <= 0 || $count > 2) {
                    throw new InvalidArgumentException(sprintf('Option "auth_ntlm" must contain 1 or 2 elements, %d given.', $count));
                }

                $options['auth_ntlm'] = implode(':', $options['auth_ntlm']);
            }

            if (!\is_string($options['auth_ntlm'])) {
                throw new InvalidArgumentException(sprintf('Option "auth_ntlm" must be a string or an array, "%s" given.', get_debug_type($options['auth_ntlm'])));
            }

            $curlopts[\CURLOPT_USERPWD] = $options['auth_ntlm'];
        }

        if (!\ZEND_THREAD_SAFE) {
            $curlopts[\CURLOPT_DNS_USE_GLOBAL_CACHE] = false;
        }

        if (\defined('CURLOPT_HEADEROPT') && \defined('CURLHEADER_SEPARATE')) {
            $curlopts[\CURLOPT_HEADEROPT] = \CURLHEADER_SEPARATE;
        }

        // curl's resolve feature varies by host:port but ours varies by host only, let's handle this with our own DNS map
        if (isset($this->multi->dnsCache->hostnames[$host])) {
            $options['resolve'] += [$host => $this->multi->dnsCache->hostnames[$host]];
        }

        if ($options['resolve'] || $this->multi->dnsCache->evictions) {
            // First reset any old DNS cache entries then add the new ones
            $resolve = $this->multi->dnsCache->evictions;
            $this->multi->dnsCache->evictions = [];
            $port = parse_url($authority, \PHP_URL_PORT) ?: ('http:' === $scheme ? 80 : 443);

            if ($resolve && 0x072A00 > self::$curlVersion['version_number']) {
                // DNS cache removals require curl 7.42 or higher
                // On lower versions, we have to create a new multi handle
                curl_multi_close($this->multi->handle);
                $this->multi->handle = (new self())->multi->handle;
            }

            foreach ($options['resolve'] as $host => $ip) {
                $resolve[] = null === $ip ? "-$host:$port" : "$host:$port:$ip";
                $this->multi->dnsCache->hostnames[$host] = $ip;
                $this->multi->dnsCache->removals["-$host:$port"] = "-$host:$port";
            }

            $curlopts[\CURLOPT_RESOLVE] = $resolve;
        }

        if ('POST' === $method) {
            // Use CURLOPT_POST to have browser-like POST-to-GET redirects for 301, 302 and 303
            $curlopts[\CURLOPT_POST] = true;
        } elseif ('HEAD' === $method) {
            $curlopts[\CURLOPT_NOBODY] = true;
        } else {
            $curlopts[\CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ('\\' !== \DIRECTORY_SEPARATOR && $options['timeout'] < 1) {
            $curlopts[\CURLOPT_NOSIGNAL] = true;
        }

        if (\extension_loaded('zlib') && !isset($options['normalized_headers']['accept-encoding'])) {
            $options['headers'][] = 'Accept-Encoding: gzip'; // Expose only one encoding, some servers mess up when more are provided
        }

        foreach ($options['headers'] as $header) {
            if (':' === $header[-2] && \strlen($header) - 2 === strpos($header, ': ')) {
                // curl requires a special syntax to send empty headers
                $curlopts[\CURLOPT_HTTPHEADER][] = substr_replace($header, ';', -2);
            } else {
                $curlopts[\CURLOPT_HTTPHEADER][] = $header;
            }
        }

        // Prevent curl from sending its default Accept and Expect headers
        foreach (['accept', 'expect'] as $header) {
            if (!isset($options['normalized_headers'][$header][0])) {
                $curlopts[\CURLOPT_HTTPHEADER][] = $header.':';
            }
        }

        if (!\is_string($body = $options['body'])) {
            if (\is_resource($body)) {
                $curlopts[\CURLOPT_INFILE] = $body;
            } else {
                $eof = false;
                $buffer = '';
                $curlopts[\CURLOPT_READFUNCTION] = static function ($ch, $fd, $length) use ($body, &$buffer, &$eof) {
                    return self::readRequestBody($length, $body, $buffer, $eof);
                };
            }

            if (isset($options['normalized_headers']['content-length'][0])) {
                $curlopts[\CURLOPT_INFILESIZE] = substr($options['normalized_headers']['content-length'][0], \strlen('Content-Length: '));
            } elseif (!isset($options['normalized_headers']['transfer-encoding'])) {
                $curlopts[\CURLOPT_HTTPHEADER][] = 'Transfer-Encoding: chunked'; // Enable chunked request bodies
            }

            if ('POST' !== $method) {
                $curlopts[\CURLOPT_UPLOAD] = true;
            }
        } elseif ('' !== $body || 'POST' === $method) {
            $curlopts[\CURLOPT_POSTFIELDS] = $body;
        }

        if ($options['peer_fingerprint']) {
            if (!isset($options['peer_fingerprint']['pin-sha256'])) {
                throw new TransportException(__CLASS__.' supports only "pin-sha256" fingerprints.');
            }

            $curlopts[\CURLOPT_PINNEDPUBLICKEY] = 'sha256//'.implode(';sha256//', $options['peer_fingerprint']['pin-sha256']);
        }

        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                $curlopts[\CURLOPT_UNIX_SOCKET_PATH] = $options['bindto'];
            } elseif (!str_starts_with($options['bindto'], 'if!') && preg_match('/^(.*):(\d+)$/', $options['bindto'], $matches)) {
                $curlopts[\CURLOPT_INTERFACE] = $matches[1];
                $curlopts[\CURLOPT_LOCALPORT] = $matches[2];
            } else {
                $curlopts[\CURLOPT_INTERFACE] = $options['bindto'];
            }
        }

        if (0 < $options['max_duration']) {
            $curlopts[\CURLOPT_TIMEOUT_MS] = 1000 * $options['max_duration'];
        }

        if (!empty($options['extra']['curl']) && \is_array($options['extra']['curl'])) {
            $this->validateExtraCurlOptions($options['extra']['curl']);
            $curlopts += $options['extra']['curl'];
        }

        if ($pushedResponse = $this->multi->pushedResponses[$url] ?? null) {
            unset($this->multi->pushedResponses[$url]);

            if (self::acceptPushForRequest($method, $options, $pushedResponse)) {
                $this->logger && $this->logger->debug(sprintf('Accepting pushed response: "%s %s"', $method, $url));

                // Reinitialize the pushed response with request's options
                $ch = $pushedResponse->handle;
                $pushedResponse = $pushedResponse->response;
                $pushedResponse->__construct($this->multi, $url, $options, $this->logger);
            } else {
                $this->logger && $this->logger->debug(sprintf('Rejecting pushed response: "%s"', $url));
                $pushedResponse = null;
            }
        }

        if (!$pushedResponse) {
            $ch = curl_init();
            $this->logger && $this->logger->info(sprintf('Request: "%s %s"', $method, $url));
        }

        foreach ($curlopts as $opt => $value) {
            if (null !== $value && !curl_setopt($ch, $opt, $value) && \CURLOPT_CERTINFO !== $opt && (!\defined('CURLOPT_HEADEROPT') || \CURLOPT_HEADEROPT !== $opt)) {
                $constantName = $this->findConstantName($opt);
                throw new TransportException(sprintf('Curl option "%s" is not supported.', $constantName ?? $opt));
            }
        }

        return $pushedResponse ?? new CurlResponse($this->multi, $ch, $options, $this->logger, $method, self::createRedirectResolver($options, $host), self::$curlVersion['version_number']);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof CurlResponse) {
            $responses = [$responses];
        } elseif (!is_iterable($responses)) {
            throw new \TypeError(sprintf('"%s()" expects parameter 1 to be an iterable of CurlResponse objects, "%s" given.', __METHOD__, get_debug_type($responses)));
        }

        if (\is_resource($this->multi->handle) || $this->multi->handle instanceof \CurlMultiHandle) {
            $active = 0;
            while (\CURLM_CALL_MULTI_PERFORM === curl_multi_exec($this->multi->handle, $active));
        }

        return new ResponseStream(CurlResponse::stream($responses, $timeout));
    }

    public function reset()
    {
        $this->multi->logger = $this->logger;
        $this->multi->reset();
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->multi->logger = $this->logger;
    }

    private function handlePush($parent, $pushed, array $requestHeaders, int $maxPendingPushes): int
    {
        $headers = [];
        $origin = curl_getinfo($parent, \CURLINFO_EFFECTIVE_URL);

        foreach ($requestHeaders as $h) {
            if (false !== $i = strpos($h, ':', 1)) {
                $headers[substr($h, 0, $i)][] = substr($h, 1 + $i);
            }
        }

        if (!isset($headers[':method']) || !isset($headers[':scheme']) || !isset($headers[':authority']) || !isset($headers[':path'])) {
            $this->logger && $this->logger->debug(sprintf('Rejecting pushed response from "%s": pushed headers are invalid', $origin));

            return \CURL_PUSH_DENY;
        }

        $url = $headers[':scheme'][0].'://'.$headers[':authority'][0];

        // curl before 7.65 doesn't validate the pushed ":authority" header,
        // but this is a MUST in the HTTP/2 RFC; let's restrict pushes to the original host,
        // ignoring domains mentioned as alt-name in the certificate for now (same as curl).
        if (!str_starts_with($origin, $url.'/')) {
            $this->logger && $this->logger->debug(sprintf('Rejecting pushed response from "%s": server is not authoritative for "%s"', $origin, $url));

            return \CURL_PUSH_DENY;
        }

        if ($maxPendingPushes <= \count($this->multi->pushedResponses)) {
            $fifoUrl = key($this->multi->pushedResponses);
            unset($this->multi->pushedResponses[$fifoUrl]);
            $this->logger && $this->logger->debug(sprintf('Evicting oldest pushed response: "%s"', $fifoUrl));
        }

        $url .= $headers[':path'][0];
        $this->logger && $this->logger->debug(sprintf('Queueing pushed response: "%s"', $url));

        $this->multi->pushedResponses[$url] = new PushedResponse(new CurlResponse($this->multi, $pushed), $headers, $this->multi->openHandles[(int) $parent][1] ?? [], $pushed);

        return \CURL_PUSH_OK;
    }

    /**
     * Accepts pushed responses only if their headers related to authentication match the request.
     */
    private static function acceptPushForRequest(string $method, array $options, PushedResponse $pushedResponse): bool
    {
        if ('' !== $options['body'] || $method !== $pushedResponse->requestHeaders[':method'][0]) {
            return false;
        }

        foreach (['proxy', 'no_proxy', 'bindto', 'local_cert', 'local_pk'] as $k) {
            if ($options[$k] !== $pushedResponse->parentOptions[$k]) {
                return false;
            }
        }

        foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
            $normalizedHeaders = $options['normalized_headers'][$k] ?? [];
            foreach ($normalizedHeaders as $i => $v) {
                $normalizedHeaders[$i] = substr($v, \strlen($k) + 2);
            }

            if (($pushedResponse->requestHeaders[$k] ?? []) !== $normalizedHeaders) {
                return false;
            }
        }

        return true;
    }

    /**
     * Wraps the request's body callback to allow it to return strings longer than curl requested.
     */
    private static function readRequestBody(int $length, \Closure $body, string &$buffer, bool &$eof): string
    {
        if (!$eof && \strlen($buffer) < $length) {
            if (!\is_string($data = $body($length))) {
                throw new TransportException(sprintf('The return value of the "body" option callback must be a string, "%s" returned.', get_debug_type($data)));
            }

            $buffer .= $data;
            $eof = '' === $data;
        }

        $data = substr($buffer, 0, $length);
        $buffer = substr($buffer, $length);

        return $data;
    }

    /**
     * Resolves relative URLs on redirects and deals with authentication headers.
     *
     * Work around CVE-2018-1000007: Authorization and Cookie headers should not follow redirects - fixed in Curl 7.64
     */
    private static function createRedirectResolver(array $options, string $host): \Closure
    {
        $redirectHeaders = [];
        if (0 < $options['max_redirects']) {
            $redirectHeaders['host'] = $host;
            $redirectHeaders['with_auth'] = $redirectHeaders['no_auth'] = array_filter($options['headers'], static function ($h) {
                return 0 !== stripos($h, 'Host:');
            });

            if (isset($options['normalized_headers']['authorization'][0]) || isset($options['normalized_headers']['cookie'][0])) {
                $redirectHeaders['no_auth'] = array_filter($options['headers'], static function ($h) {
                    return 0 !== stripos($h, 'Authorization:') && 0 !== stripos($h, 'Cookie:');
                });
            }
        }

        return static function ($ch, string $location) use ($redirectHeaders) {
            try {
                $location = self::parseUrl($location);
            } catch (InvalidArgumentException $e) {
                return null;
            }

            if ($redirectHeaders && $host = parse_url('http:'.$location['authority'], \PHP_URL_HOST)) {
                $requestHeaders = $redirectHeaders['host'] === $host ? $redirectHeaders['with_auth'] : $redirectHeaders['no_auth'];
                curl_setopt($ch, \CURLOPT_HTTPHEADER, $requestHeaders);
            }

            $url = self::parseUrl(curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL));

            return implode('', self::resolveUrl($location, $url));
        };
    }

    private function findConstantName(int $opt): ?string
    {
        $constants = array_filter(get_defined_constants(), static function ($v, $k) use ($opt) {
            return $v === $opt && 'C' === $k[0] && (str_starts_with($k, 'CURLOPT_') || str_starts_with($k, 'CURLINFO_'));
        }, \ARRAY_FILTER_USE_BOTH);

        return key($constants);
    }

    /**
     * Prevents overriding options that are set internally throughout the request.
     */
    private function validateExtraCurlOptions(array $options): void
    {
        $curloptsToConfig = [
            //options used in CurlHttpClient
            \CURLOPT_HTTPAUTH => 'auth_ntlm',
            \CURLOPT_USERPWD => 'auth_ntlm',
            \CURLOPT_RESOLVE => 'resolve',
            \CURLOPT_NOSIGNAL => 'timeout',
            \CURLOPT_HTTPHEADER => 'headers',
            \CURLOPT_INFILE => 'body',
            \CURLOPT_READFUNCTION => 'body',
            \CURLOPT_INFILESIZE => 'body',
            \CURLOPT_POSTFIELDS => 'body',
            \CURLOPT_UPLOAD => 'body',
            \CURLOPT_PINNEDPUBLICKEY => 'peer_fingerprint',
            \CURLOPT_UNIX_SOCKET_PATH => 'bindto',
            \CURLOPT_INTERFACE => 'bindto',
            \CURLOPT_TIMEOUT_MS => 'max_duration',
            \CURLOPT_TIMEOUT => 'max_duration',
            \CURLOPT_MAXREDIRS => 'max_redirects',
            \CURLOPT_PROXY => 'proxy',
            \CURLOPT_NOPROXY => 'no_proxy',
            \CURLOPT_SSL_VERIFYPEER => 'verify_peer',
            \CURLOPT_SSL_VERIFYHOST => 'verify_host',
            \CURLOPT_CAINFO => 'cafile',
            \CURLOPT_CAPATH => 'capath',
            \CURLOPT_SSL_CIPHER_LIST => 'ciphers',
            \CURLOPT_SSLCERT => 'local_cert',
            \CURLOPT_SSLKEY => 'local_pk',
            \CURLOPT_KEYPASSWD => 'passphrase',
            \CURLOPT_CERTINFO => 'capture_peer_cert_chain',
            \CURLOPT_USERAGENT => 'normalized_headers',
            \CURLOPT_REFERER => 'headers',
            //options used in CurlResponse
            \CURLOPT_NOPROGRESS => 'on_progress',
            \CURLOPT_PROGRESSFUNCTION => 'on_progress',
        ];

        $curloptsToCheck = [
            \CURLOPT_PRIVATE,
            \CURLOPT_HEADERFUNCTION,
            \CURLOPT_WRITEFUNCTION,
            \CURLOPT_VERBOSE,
            \CURLOPT_STDERR,
            \CURLOPT_RETURNTRANSFER,
            \CURLOPT_URL,
            \CURLOPT_FOLLOWLOCATION,
            \CURLOPT_HEADER,
            \CURLOPT_CONNECTTIMEOUT,
            \CURLOPT_CONNECTTIMEOUT_MS,
            \CURLOPT_HTTP_VERSION,
            \CURLOPT_PORT,
            \CURLOPT_DNS_USE_GLOBAL_CACHE,
            \CURLOPT_PROTOCOLS,
            \CURLOPT_REDIR_PROTOCOLS,
            \CURLOPT_COOKIEFILE,
            \CURLINFO_REDIRECT_COUNT,
        ];

        if (\defined('CURLOPT_HTTP09_ALLOWED')) {
            $curloptsToCheck[] = \CURLOPT_HTTP09_ALLOWED;
        }

        if (\defined('CURLOPT_HEADEROPT')) {
            $curloptsToCheck[] = \CURLOPT_HEADEROPT;
        }

        $methodOpts = [
            \CURLOPT_POST,
            \CURLOPT_PUT,
            \CURLOPT_CUSTOMREQUEST,
            \CURLOPT_HTTPGET,
            \CURLOPT_NOBODY,
        ];

        foreach ($options as $opt => $optValue) {
            if (isset($curloptsToConfig[$opt])) {
                $constName = $this->findConstantName($opt) ?? $opt;
                throw new InvalidArgumentException(sprintf('Cannot set "%s" with "extra.curl", use option "%s" instead.', $constName, $curloptsToConfig[$opt]));
            }

            if (\in_array($opt, $methodOpts)) {
                throw new InvalidArgumentException('The HTTP method cannot be overridden using "extra.curl".');
            }

            if (\in_array($opt, $curloptsToCheck)) {
                $constName = $this->findConstantName($opt) ?? $opt;
                throw new InvalidArgumentException(sprintf('Cannot set "%s" with "extra.curl".', $constName));
            }
        }
    }
}
