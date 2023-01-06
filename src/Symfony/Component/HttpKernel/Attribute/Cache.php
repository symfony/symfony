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

/**
 * Describes the default HTTP cache headers on controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Cache
{
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
         * Whether the response is public or not.
         */
        public ?bool $public = null,

        /**
         * Whether or not the response must be revalidated.
         */
        public bool $mustRevalidate = false,

        /**
         * Additional "Vary:"-headers.
         */
        public array $vary = [],

        /**
         * An expression to compute the Last-Modified HTTP header.
         */
        public ?string $lastModified = null,

        /**
         * An expression to compute the ETag HTTP header.
         */
        public ?string $etag = null,

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
    ) {
    }
}
