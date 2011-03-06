<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This code is partially based on the Rack-Cache library by Ryan Tomayko,
 * which is released under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface StoreInterface
{
    /**
     * Locates a cached Response for the Request provided.
     *
     * @param Request $request A Request instance
     *
     * @return Response|null A Response instance, or null if no cache entry was found
     */
    function lookup(Request $request);

    /**
     * Writes a cache entry to the store for the given Request and Response.
     *
     * Existing entries are read and any that match the response are removed. This
     * method calls write with the new list of cache entries.
     *
     * @param Request  $request  A Request instance
     * @param Response $response A Response instance
     *
     * @return string The key under which the response is stored
     */
    function write(Request $request, Response $response);

    /**
     * Invalidates all cache entries that match the request.
     *
     * @param Request $request A Request instance
     */
    function invalidate(Request $request);

    /**
     * Locks the cache for a given Request.
     *
     * @param Request $request A Request instance
     *
     * @return Boolean|string true if the lock is acquired, the path to the current lock otherwise
     */
    function lock(Request $request);

    /**
     * Releases the lock for the given Request.
     *
     * @param Request $request A Request instance
     */
    function unlock(Request $request);

    /**
     * Purges data for the given URL.
     *
     * @param string $url A URL
     *
     * @return Boolean true if the URL exists and has been purged, false otherwise
     */
    function purge($url);

    /**
     * Cleanups storage.
     */
    function cleanup();
}
