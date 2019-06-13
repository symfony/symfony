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

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;

/**
 * Provides the common logic from writing HttpClientInterface implementations.
 *
 * All methods are static to prevent implementers from creating memory leaks via circular references.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.3
 */
trait HttpClientTrait
{
    private static $CHUNK_SIZE = 16372;

    /**
     * Validates and normalizes method, URL and options, and merges them with defaults.
     *
     * @throws InvalidArgumentException When a not-supported option is found
     */
    private static function prepareRequest(?string $method, ?string $url, array $options, array $defaultOptions = [], bool $allowExtraOptions = false): array
    {
        if (null !== $method) {
            if (\strlen($method) !== strspn($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) {
                throw new InvalidArgumentException(sprintf('Invalid HTTP method "%s", only uppercase letters are accepted.', $method));
            }
            if (!$method) {
                throw new InvalidArgumentException('The HTTP method can not be empty.');
            }
        }

        $options = self::mergeDefaultOptions($options, $defaultOptions, $allowExtraOptions);

        if (isset($options['json'])) {
            if (isset($options['body']) && '' !== $options['body']) {
                throw new InvalidArgumentException('Define either the "json" or the "body" option, setting both is not supported.');
            }
            $options['body'] = self::jsonEncode($options['json']);
            unset($options['json']);
            $options['headers']['content-type'] = $options['headers']['content-type'] ?? ['application/json'];
        }

        if (isset($options['body'])) {
            $options['body'] = self::normalizeBody($options['body']);
        }

        if (isset($options['peer_fingerprint'])) {
            $options['peer_fingerprint'] = self::normalizePeerFingerprint($options['peer_fingerprint']);
        }

        // Compute request headers
        $requestHeaders = $headers = [];

        foreach ($options['headers'] as $name => $values) {
            foreach ($values as $value) {
                $requestHeaders[] = $name.': '.$headers[$name][] = $value = (string) $value;

                if (\strlen($value) !== strcspn($value, "\r\n\0")) {
                    throw new InvalidArgumentException(sprintf('Invalid header value: CR/LF/NUL found in "%s".', $value));
                }
            }
        }

        // Validate on_progress
        if (!\is_callable($onProgress = $options['on_progress'] ?? 'var_dump')) {
            throw new InvalidArgumentException(sprintf('Option "on_progress" must be callable, %s given.', \is_object($onProgress) ? \get_class($onProgress) : \gettype($onProgress)));
        }

        if (\is_array($options['auth_basic'] ?? null)) {
            $count = \count($options['auth_basic']);
            if ($count <= 0 || $count > 2) {
                throw new InvalidArgumentException(sprintf('Option "auth_basic" must contain 1 or 2 elements, %s given.', $count));
            }

            $options['auth_basic'] = implode(':', $options['auth_basic']);
        }

        if (!\is_string($options['auth_basic'] ?? '')) {
            throw new InvalidArgumentException(sprintf('Option "auth_basic" must be string or an array, %s given.', \gettype($options['auth_basic'])));
        }

        if (isset($options['auth_bearer']) && (!\is_string($options['auth_bearer']) || !preg_match('{^[-._~+/0-9a-zA-Z]++=*+$}', $options['auth_bearer']))) {
            throw new InvalidArgumentException(sprintf('Option "auth_bearer" must be a string containing only characters from the base 64 alphabet, %s given.', \is_string($options['auth_bearer']) ? 'invalid string' : \gettype($options['auth_bearer'])));
        }

        if (isset($options['auth_basic'], $options['auth_bearer'])) {
            throw new InvalidArgumentException('Define either the "auth_basic" or the "auth_bearer" option, setting both is not supported.');
        }

        if (null !== $url) {
            // Merge auth with headers
            if (($options['auth_basic'] ?? false) && !($headers['authorization'] ?? false)) {
                $requestHeaders[] = 'authorization: '.$headers['authorization'][] = 'Basic '.base64_encode($options['auth_basic']);
            }
            // Merge bearer with headers
            if (($options['auth_bearer'] ?? false) && !($headers['authorization'] ?? false)) {
                $requestHeaders[] = 'authorization: '.$headers['authorization'][] = 'Bearer '.$options['auth_bearer'];
            }

            $options['request_headers'] = $requestHeaders;
            unset($options['auth_basic'], $options['auth_bearer']);

            // Parse base URI
            if (\is_string($options['base_uri'])) {
                $options['base_uri'] = self::parseUrl($options['base_uri']);
            }

            // Validate and resolve URL
            $url = self::parseUrl($url, $options['query']);
            $url = self::resolveUrl($url, $options['base_uri'], $defaultOptions['query'] ?? []);
        }

        // Finalize normalization of options
        $options['headers'] = $headers;
        $options['http_version'] = (string) ($options['http_version'] ?? '') ?: null;
        $options['timeout'] = (float) ($options['timeout'] ?? ini_get('default_socket_timeout'));

        return [$url, $options];
    }

    /**
     * @throws InvalidArgumentException When an invalid option is found
     */
    private static function mergeDefaultOptions(array $options, array $defaultOptions, bool $allowExtraOptions = false): array
    {
        unset($options['request_headers'], $defaultOptions['request_headers']);

        $options['headers'] = self::normalizeHeaders($options['headers'] ?? []);

        if ($defaultOptions['headers'] ?? false) {
            $options['headers'] += self::normalizeHeaders($defaultOptions['headers']);
        }

        if ($options['resolve'] ?? false) {
            $options['resolve'] = array_change_key_case($options['resolve']);
        }

        // Option "query" is never inherited from defaults
        $options['query'] = $options['query'] ?? [];

        foreach ($defaultOptions as $k => $v) {
            $options[$k] = $options[$k] ?? $v;
        }

        if (isset($defaultOptions['extra'])) {
            $options['extra'] += $defaultOptions['extra'];
        }

        if ($defaultOptions['resolve'] ?? false) {
            $options['resolve'] += array_change_key_case($defaultOptions['resolve']);
        }

        if ($allowExtraOptions || !$defaultOptions) {
            return $options;
        }

        // Look for unsupported options
        foreach ($options as $name => $v) {
            if (\array_key_exists($name, $defaultOptions)) {
                continue;
            }

            $alternatives = [];

            foreach ($defaultOptions as $key => $v) {
                if (levenshtein($name, $key) <= \strlen($name) / 3 || false !== strpos($key, $name)) {
                    $alternatives[] = $key;
                }
            }

            throw new InvalidArgumentException(sprintf('Unsupported option "%s" passed to %s, did you mean "%s"?', $name, __CLASS__, implode('", "', $alternatives ?: array_keys($defaultOptions))));
        }

        return $options;
    }

    /**
     * Normalizes headers by putting their names as lowercased keys.
     *
     * @return string[][]
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $name => $values) {
            if (\is_int($name)) {
                [$name, $values] = explode(':', $values, 2);
                $values = [ltrim($values)];
            } elseif (!is_iterable($values)) {
                $values = (array) $values;
            }

            $normalizedHeaders[$name = strtolower($name)] = [];

            foreach ($values as $value) {
                $normalizedHeaders[$name][] = $value;
            }
        }

        return $normalizedHeaders;
    }

    /**
     * @param array|string|resource|\Traversable|\Closure $body
     *
     * @return string|resource|\Closure
     *
     * @throws InvalidArgumentException When an invalid body is passed
     */
    private static function normalizeBody($body)
    {
        if (\is_array($body)) {
            return http_build_query($body, '', '&', PHP_QUERY_RFC1738);
        }

        if ($body instanceof \Traversable) {
            $body = function () use ($body) { yield from $body; };
        }

        if ($body instanceof \Closure) {
            $r = new \ReflectionFunction($body);
            $body = $r->getClosure();

            if ($r->isGenerator()) {
                $body = $body(self::$CHUNK_SIZE);
                $body = function () use ($body) {
                    while ($body->valid()) {
                        $chunk = $body->current();
                        $body->next();

                        if ('' !== $chunk) {
                            return $chunk;
                        }
                    }

                    return '';
                };
            }

            return $body;
        }

        if (!\is_string($body) && !\is_array(@stream_get_meta_data($body))) {
            throw new InvalidArgumentException(sprintf('Option "body" must be string, stream resource, iterable or callable, %s given.', \is_resource($body) ? get_resource_type($body) : \gettype($body)));
        }

        return $body;
    }

    /**
     * @param string|string[] $fingerprint
     *
     * @throws InvalidArgumentException When an invalid fingerprint is passed
     */
    private static function normalizePeerFingerprint($fingerprint): array
    {
        if (\is_string($fingerprint)) {
            switch (\strlen($fingerprint = str_replace(':', '', $fingerprint))) {
                case 32: $fingerprint = ['md5' => $fingerprint]; break;
                case 40: $fingerprint = ['sha1' => $fingerprint]; break;
                case 44: $fingerprint = ['pin-sha256' => [$fingerprint]]; break;
                case 64: $fingerprint = ['sha256' => $fingerprint]; break;
                default: throw new InvalidArgumentException(sprintf('Cannot auto-detect fingerprint algorithm for "%s".', $fingerprint));
            }
        } elseif (\is_array($fingerprint)) {
            foreach ($fingerprint as $algo => $hash) {
                $fingerprint[$algo] = 'pin-sha256' === $algo ? (array) $hash : str_replace(':', '', $hash);
            }
        } else {
            throw new InvalidArgumentException(sprintf('Option "peer_fingerprint" must be string or array, %s given.', \gettype($fingerprint)));
        }

        return $fingerprint;
    }

    /**
     * @param array|\JsonSerializable $value
     *
     * @throws InvalidArgumentException When the value cannot be json-encoded
     */
    private static function jsonEncode($value, int $flags = null, int $maxDepth = 512): string
    {
        $flags = $flags ?? (JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION);

        if (!\is_array($value) && !$value instanceof \JsonSerializable) {
            throw new InvalidArgumentException(sprintf('Option "json" must be array or JsonSerializable, %s given.', \is_object($value) ? \get_class($value) : \gettype($value)));
        }

        try {
            $value = json_encode($value, $flags | (\PHP_VERSION_ID >= 70300 ? \JSON_THROW_ON_ERROR : 0), $maxDepth);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(sprintf('Invalid value for "json" option: %s.', $e->getMessage()));
        }

        if (\PHP_VERSION_ID < 70300 && JSON_ERROR_NONE !== json_last_error() && (false === $value || !($flags & JSON_PARTIAL_OUTPUT_ON_ERROR))) {
            throw new InvalidArgumentException(sprintf('Invalid value for "json" option: %s.', json_last_error_msg()));
        }

        return $value;
    }

    /**
     * Resolves a URL against a base URI.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-5.2.2
     *
     * @throws InvalidArgumentException When an invalid URL is passed
     */
    private static function resolveUrl(array $url, ?array $base, array $queryDefaults = []): array
    {
        if (null !== $base && '' === ($base['scheme'] ?? '').($base['authority'] ?? '')) {
            throw new InvalidArgumentException(sprintf('Invalid "base_uri" option: host or scheme is missing in "%s".', implode('', $base)));
        }

        if (null === $base && '' === $url['scheme'].$url['authority']) {
            throw new InvalidArgumentException(sprintf('Invalid URL: no "base_uri" option was provided and host or scheme is missing in "%s".', implode('', $url)));
        }

        if (null !== $url['scheme']) {
            $url['path'] = self::removeDotSegments($url['path'] ?? '');
        } else {
            if (null !== $url['authority']) {
                $url['path'] = self::removeDotSegments($url['path'] ?? '');
            } else {
                if (null === $url['path']) {
                    $url['path'] = $base['path'];
                    $url['query'] = $url['query'] ?? $base['query'];
                } else {
                    if ('/' !== $url['path'][0]) {
                        if (null === $base['path']) {
                            $url['path'] = '/'.$url['path'];
                        } else {
                            $segments = explode('/', $base['path']);
                            array_splice($segments, -1, 1, [$url['path']]);
                            $url['path'] = implode('/', $segments);
                        }
                    }

                    $url['path'] = self::removeDotSegments($url['path']);
                }

                $url['authority'] = $base['authority'];

                if ($queryDefaults) {
                    $url['query'] = '?'.self::mergeQueryString(substr($url['query'] ?? '', 1), $queryDefaults, false);
                }
            }

            $url['scheme'] = $base['scheme'];
        }

        if ('' === ($url['path'] ?? '')) {
            $url['path'] = '/';
        }

        return $url;
    }

    /**
     * Parses a URL and fixes its encoding if needed.
     *
     * @throws InvalidArgumentException When an invalid URL is passed
     */
    private static function parseUrl(string $url, array $query = [], array $allowedSchemes = ['http' => 80, 'https' => 443]): array
    {
        if (false === $parts = parse_url($url)) {
            throw new InvalidArgumentException(sprintf('Malformed URL "%s".', $url));
        }

        if ($query) {
            $parts['query'] = self::mergeQueryString($parts['query'] ?? null, $query, true);
        }

        $port = $parts['port'] ?? 0;

        if (null !== $scheme = $parts['scheme'] ?? null) {
            if (!isset($allowedSchemes[$scheme = strtolower($scheme)])) {
                throw new InvalidArgumentException(sprintf('Unsupported scheme in "%s".', $url));
            }

            $port = $allowedSchemes[$scheme] === $port ? 0 : $port;
            $scheme .= ':';
        }

        if (null !== $host = $parts['host'] ?? null) {
            if (!\defined('INTL_IDNA_VARIANT_UTS46') && preg_match('/[\x80-\xFF]/', $host)) {
                throw new InvalidArgumentException(sprintf('Unsupported IDN "%s", try enabling the "intl" PHP extension or running "composer require symfony/polyfill-intl-idn".', $host));
            }

            if (false === $host = \defined('INTL_IDNA_VARIANT_UTS46') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : strtolower($host)) {
                throw new InvalidArgumentException(sprintf('Unsupported host in "%s".', $url));
            }

            $host .= $port ? ':'.$port : '';
        }

        foreach (['user', 'pass', 'path', 'query', 'fragment'] as $part) {
            if (!isset($parts[$part])) {
                continue;
            }

            if (false !== strpos($parts[$part], '%')) {
                // https://tools.ietf.org/html/rfc3986#section-2.3
                $parts[$part] = preg_replace_callback('/%(?:2[DE]|3[0-9]|[46][1-9A-F]|5F|[57][0-9A]|7E)++/i', function ($m) { return rawurldecode($m[0]); }, $parts[$part]);
            }

            // https://tools.ietf.org/html/rfc3986#section-3.3
            $parts[$part] = preg_replace_callback("#[^-A-Za-z0-9._~!$&/'()*+,;=:@%]++#", function ($m) { return rawurlencode($m[0]); }, $parts[$part]);
        }

        return [
            'scheme' => $scheme,
            'authority' => null !== $host ? '//'.(isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '').$host : null,
            'path' => isset($parts['path'][0]) ? $parts['path'] : null,
            'query' => isset($parts['query']) ? '?'.$parts['query'] : null,
            'fragment' => isset($parts['fragment']) ? '#'.$parts['fragment'] : null,
        ];
    }

    /**
     * Removes dot-segments from a path.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    private static function removeDotSegments(string $path)
    {
        $result = '';

        while (!\in_array($path, ['', '.', '..'], true)) {
            if ('.' === $path[0] && (0 === strpos($path, $p = '../') || 0 === strpos($path, $p = './'))) {
                $path = substr($path, \strlen($p));
            } elseif ('/.' === $path || 0 === strpos($path, '/./')) {
                $path = substr_replace($path, '/', 0, 3);
            } elseif ('/..' === $path || 0 === strpos($path, '/../')) {
                $i = strrpos($result, '/');
                $result = $i ? substr($result, 0, $i) : '';
                $path = substr_replace($path, '/', 0, 4);
            } else {
                $i = strpos($path, '/', 1) ?: \strlen($path);
                $result .= substr($path, 0, $i);
                $path = substr($path, $i);
            }
        }

        return $result;
    }

    /**
     * Merges and encodes a query array with a query string.
     *
     * @throws InvalidArgumentException When an invalid query-string value is passed
     */
    private static function mergeQueryString(?string $queryString, array $queryArray, bool $replace): ?string
    {
        if (!$queryArray) {
            return $queryString;
        }

        $query = [];

        if (null !== $queryString) {
            foreach (explode('&', $queryString) as $v) {
                if ('' !== $v) {
                    $k = urldecode(explode('=', $v, 2)[0]);
                    $query[$k] = (isset($query[$k]) ? $query[$k].'&' : '').$v;
                }
            }
        }

        if ($replace) {
            foreach ($queryArray as $k => $v) {
                if (null === $v) {
                    unset($query[$k]);
                }
            }
        }

        $queryString = http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
        $queryArray = [];

        if ($queryString) {
            foreach (explode('&', $queryString) as $v) {
                $queryArray[rawurldecode(explode('=', $v, 2)[0])] = $v;
            }
        }

        return implode('&', $replace ? array_replace($query, $queryArray) : ($query + $queryArray));
    }
}
