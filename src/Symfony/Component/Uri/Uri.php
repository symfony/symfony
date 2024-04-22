<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri;

use Symfony\Component\Uri\Exception\InvalidUriException;
use Symfony\Component\Uri\Exception\UnresolvableUriException;

/**
 * Parses a URI and allows to resolve relative URIs, as defined
 * in RFC 3986 (https://tools.ietf.org/html/rfc3986).
 *
 * @experimental
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class Uri implements \Stringable
{
    private bool $hasDoubleSlashAuthority = false;

    private const URI_GLOBAL_REGEX = '/^(?:(?P<scheme>[^:\/?#]+):)?(?:\/\/(?P<authority>[^\/?#]*))?(?P<path>[^?#]*)(?:\?(?P<query>[^#]*))?(?:#(?P<fragment>.*))?$/';
    private const URI_AUTHORITY_REGEX = '/^(?:(?P<user>[^:@]*)(?::(?P<pass>[^@]*))?@)?(?P<host>[^:]*)(?::(?P<port>\d*))?$/';

    public function __construct(
        public string $scheme,
        #[\SensitiveParameter]
        public ?string $user = null,
        #[\SensitiveParameter]
        public ?string $password = null,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $path = null,
        public ?QueryString $query = null,
        public ?string $fragment = null,
        public ?FragmentTextDirective $fragmentTextDirective = null,
    ) {
    }

    /**
     * Parses a URL.
     *
     * The `user` and `pass` keys are url-decoded automatically when parsing.
     *
     * @throws InvalidUriException
     */
    public static function parse(#[\SensitiveParameter] string $uri): static
    {
        preg_match(self::URI_GLOBAL_REGEX, $uri, $matches);
        if (!$matches || !isset($matches['scheme']) || '' === $matches['scheme']) {
            throw new InvalidUriException($uri);
        }

        if (preg_match('~'.$matches['scheme'].':/(?!/)~', $uri)) {
            throw new InvalidUriException($uri);
        }

        if (isset($matches['authority'])) {
            if (!str_contains($uri, '://') && '' !== $matches['authority']) {
                throw new InvalidUriException($uri);
            }

            preg_match(self::URI_AUTHORITY_REGEX, $matches['authority'], $authMatches);

            $matches = array_merge($matches, $authMatches);
            unset($matches['authority']);
        }

        $matches = array_filter($matches, static fn (string $value): bool => '' !== $value);

        $uriInstance = new static(
            $matches['scheme'],
            isset($matches['user']) ? rawurldecode($matches['user']) : null,
            isset($matches['pass']) ? rawurldecode($matches['pass']) : null,
            $matches['host'] ?? null,
            $matches['port'] ?? null,
            $matches['path'] ?? null,
            isset($matches['query']) ? QueryString::parse($matches['query']) : null,
            $matches['fragment'] ?? null,
        );

        if (str_contains($uri, '://')) {
            $uriInstance->hasDoubleSlashAuthority = true;
        }

        return $uriInstance;
    }

    /**
     * Resolves a relative URI against a base URI.
     *
     * Uri::resolve('/foo/bar', 'http://example.com'); // http://example.com/foo/bar
     * Uri::resolve('/bar', 'http://example.com/foo'); // http://example.com/bar
     * Uri::resolve('bar', 'http://example.com/foo'); // http://example.com/bar
     * Uri::resolve('bar', 'http://example.com/foo/'); // http://example.com/foo/bar
     * Uri::resolve('../bar', 'http://example.com/foo/'); // http://example.com/bar
     * Uri::resolve('../../bar', 'http://example.com/foo/'); // http://example.com/bar
     * Uri::resolve('/bar', 'http://example.com/foo/'); // http://example.com/bar
     * Uri::resolve('http://example.org/bar', 'http://example.com/foo/'); // http://example.org/bar
     */
    public static function resolve(string $relativeUri, self|string $baseUri): string
    {
        if ('' === $relativeUri) {
            return (string) $baseUri;
        }

        // the relative URI is an absolute URI
        if (preg_match('/^[a-zA-Z][a-zA-Z\d+\-.]*:/', $relativeUri)) {
            return $relativeUri;
        }

        $baseUri = $baseUri instanceof self ? $baseUri : self::parse($baseUri);
        if (!$baseUri->hasDoubleSlashAuthority) {
            throw new UnresolvableUriException((string) $baseUri);
        }

        $baseUri->query = null;
        $baseUri->fragment = null;
        $baseUri->fragmentTextDirective = null;

        if (!str_ends_with($baseUri->path ?? '', '/')) {
            // when the base URI does not end with a slash, the path is erased
            $baseUri->path = null;
        }

        $relativeParts = explode('/', $relativeUri);
        $baseParts = $baseUri->path && !str_starts_with($relativeUri, '/') ?
            explode('/', trim($baseUri->path, '/'))
            : [];

        $resolvedPathSegments = $baseParts;
        foreach ($relativeParts as $segment) {
            if ('..' === $segment) {
                array_pop($resolvedPathSegments);
            } elseif ('.' !== $segment && '' !== $segment) {
                $resolvedPathSegments[] = $segment;
            }
        }

        $finalUri = clone $baseUri;
        $finalUri->path = '/'.implode('/', $resolvedPathSegments);

        return (string) $finalUri;
    }

    /**
     * Returns a new instance with a new fragment text directive.
     */
    public function withFragmentTextDirective(string $start, ?string $end = null, ?string $prefix = null, ?string $suffix = null): static
    {
        $uri = clone $this;
        $uri->fragmentTextDirective = new FragmentTextDirective($start, $end, $prefix, $suffix);

        return $uri;
    }

    /**
     * Returns a new instance with the host part of the URI converted to ASCII.
     *
     * @see https://www.unicode.org/reports/tr46/#ToASCII
     */
    public function withIdnHostAsAscii(): static
    {
        $uri = clone $this;
        $uri->host = idn_to_ascii($uri->host, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

        return $uri;
    }

    /**
     * Returns a new instance with the host part of the URI converted to Unicode.
     *
     * @see https://www.unicode.org/reports/tr46/#ToUnicode
     */
    public function withIdnHostAsUnicode(): static
    {
        $uri = clone $this;
        $uri->host = idn_to_utf8($uri->host, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);

        return $uri;
    }

    public function __toString()
    {
        return $this->scheme.':'
            .($this->hasDoubleSlashAuthority ? '//' : '')
            .(null !== $this->user ? (null !== $this->password ? rawurlencode($this->user).':'.rawurlencode($this->password) : urlencode($this->user)).'@' : '')
            .($this->host ?: '')
            .($this->port ? ':'.$this->port : '')
            .($this->path ?? '')
            .($this->query ? '?'.$this->query : '')
            .($this->fragment || $this->fragmentTextDirective ? '#' : '')
            .($this->fragment ?? '')
            .($this->fragmentTextDirective ?? '');
    }
}
