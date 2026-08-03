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
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Decides how a response is stored by CachingHttpClient.
 *
 * An instance is passed to the "extra.cache_policy" option, which is called once the
 * response headers are known:
 *
 *     $client->request('GET', $url, ['extra' => ['cache' => function (CachePolicy $cache, int $statusCode, array $headers) {
 *         $cache->tag('product-42')->expiresAfter(3600);
 *     }]]);
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CachePolicy
{
    private array $tags = [];
    private ?int $ttl = null;
    private ?\Closure $bodyTagResolver = null;

    /**
     * Tags the stored response, so that it can be invalidated with CacheInterface::invalidateTags().
     *
     * @param string|iterable<string> $tags
     */
    public function tag(string|iterable $tags): static
    {
        foreach (\is_string($tags) ? [$tags] : $tags as $tag) {
            if (!\is_string($tag) || '' === $tag) {
                throw new InvalidArgumentException(\sprintf('Cache tags must be non-empty strings, "%s" given.', get_debug_type($tag)));
            }

            if (strpbrk($tag, ItemInterface::RESERVED_CHARACTERS)) {
                throw new InvalidArgumentException(\sprintf('The cache tag "%s" contains one of the reserved characters "%s".', $tag, ItemInterface::RESERVED_CHARACTERS));
            }

            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * Stores the response for the given number of seconds, whatever its cache directives say.
     *
     * This makes a response cacheable that would not be, and replaces the freshness
     * lifetime computed from its headers. Pass null to fall back to those headers.
     */
    public function expiresAfter(?int $ttl): static
    {
        if (null !== $ttl && $ttl < 0) {
            throw new InvalidArgumentException(\sprintf('The cache TTL must be a positive number of seconds, "%d" given.', $ttl));
        }

        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Tags the stored response with tags computed from its body.
     *
     * The resolver is called once the body has been received, which requires the
     * "buffer" option to be true; the tags are dropped and a warning is logged otherwise.
     *
     * @param callable(string $body): iterable<string> $resolver
     */
    public function tagFromBody(callable $resolver): static
    {
        $this->bodyTagResolver = $resolver(...);

        return $this;
    }

    /**
     * @return list<string>
     *
     * @internal
     */
    public function getTags(): array
    {
        return array_values(array_unique($this->tags));
    }

    /**
     * @internal
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * @internal
     */
    public function getBodyTagResolver(): ?\Closure
    {
        return $this->bodyTagResolver;
    }
}
