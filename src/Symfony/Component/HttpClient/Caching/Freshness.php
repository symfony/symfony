<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Caching;

/**
 * @internal
 */
enum Freshness
{
    /**
     * The cached response is fresh and can be used without revalidation.
     */
    case Fresh;
    /**
     * The cached response is stale and must be revalidated before use.
     */
    case MustRevalidate;
    /**
     * The cached response is stale and should not be used.
     */
    case Stale;
    /**
     * The cached response is stale but may be used as a fallback in case of errors.
     */
    case StaleButUsable;
}
