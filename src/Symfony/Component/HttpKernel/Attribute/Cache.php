<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;

/**
 * Describes the default HTTP cache headers on controllers.
 * Headers defined in the Cache attribute are ignored if they are already set
 * by the controller.
 *
 * @see https://symfony.com/doc/current/http_cache.html#making-your-responses-http-cacheable
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final class Cache
{
    /**
     * @internal
     */
    public public(set) readonly array $variables;

    public function __construct(
        /**
         * The expiration date as a valid date for the strtotime() function.
         */
        public ?string $expires = null,

        /**
         * The number of seconds that the response is considered fresh by a private
         * cache like a web browser.
         */
        public int|string|null $maxage = null,

        /**
         * The number of seconds that the response is considered fresh by a public
         * cache like a reverse proxy cache.
         */
        public int|string|null $smaxage = null,

        /**
         * If true, the contents will be stored in a public cache and served to all
         * the next requests.
         */
        public ?bool $public = null,

        /**
         * If true, the response is not served stale by a cache in any circumstance
         * without first revalidating with the origin.
         */
        public bool $mustRevalidate = false,

        /**
         * Set "Vary" header.
         *
         * Example:
         * ['Accept-Encoding', 'User-Agent']
         *
         * @see https://symfony.com/doc/current/http_cache/cache_vary.html
         *
         * @var string[]
         */
        public array $vary = [],

        /**
         * A value evaluated to compute the Last-Modified HTTP header.
         *
         * The value may be either an ExpressionLanguage expression or a Closure and
         * receives all the request attributes and the resolved controller arguments.
         *
         * The result must be an instance of \DateTimeInterface.
         *
         * @var \DateTimeInterface|string|Expression|\Closure(array<string, mixed>, Request, ?object):\DateTimeInterface|null
         */
        public \DateTimeInterface|string|Expression|\Closure|null $lastModified = null,

        /**
         * A value evaluated to compute the ETag HTTP header.
         *
         * The value may be either an ExpressionLanguage expression or a Closure and
         * receives all the request attributes and the resolved controller arguments.
         *
         * The result must be a string that will be hashed.
         *
         * @var string|Expression|\Closure(array<string, mixed>, Request, ?object):string|null
         */
        public string|Expression|\Closure|null $etag = null,

        /**
         * max-stale Cache-Control header
         * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
         */
        public int|string|null $maxStale = null,

        /**
         * stale-while-revalidate Cache-Control header
         * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
         */
        public int|string|null $staleWhileRevalidate = null,

        /**
         * stale-if-error Cache-Control header
         * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
         */
        public int|string|null $staleIfError = null,

        /**
         * Add the "no-store" Cache-Control directive when set to true.
         *
         * This directive indicates that no part of the response can be cached
         * in any cache (not in a shared cache, nor in a private cache).
         *
         * Supersedes the "$public" and "$smaxage" values.
         *
         * @see https://datatracker.ietf.org/doc/html/rfc7234#section-5.2.2.3
         */
        public ?bool $noStore = null,

        /**
         * A value evaluated to determine whether the cache attribute should be applied.
         *
         * The value may be either an ExpressionLanguage expression or a Closure and
         * receives all the request attributes and the resolved controller arguments.
         *
         * The result must be a boolean. If true the attribute is applied, if false it is ignored.
         *
         * @var bool|string|Expression|\Closure(array<string, mixed>, Request, ?object):bool
         */
        public bool|string|Expression|\Closure $if = true,
    ) {
    }
}
