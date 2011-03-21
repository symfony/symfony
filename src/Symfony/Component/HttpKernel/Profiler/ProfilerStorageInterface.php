<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

/**
 * ProfilerStorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ProfilerStorageInterface
{
    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip    The IP
     * @param string $url   The URL
     * @param string $limit The maximum number of tokens to return
     *
     * @return array An array of tokens
     */
    function find($ip, $url, $limit);

    /**
     * Finds profiler tokens for the given parent token.
     *
     * @param string $token    The parent token
     *
     * @return array An array of tokens
     */
    function findChildren($token);

    /**
     * Reads data associated with the given token.
     *
     * The method returns false if the token does not exists in the storage.
     *
     * @param string $token A token
     *
     * @return string The data associated with token
     */
    function read($token);

    /**
     * Write data associated with the given token.
     *
     * @param string  $token  A token
     * @param string  $parent The parent token
     * @param string  $data   The data associated with token
     * @param string  $ip     An IP
     * @param string  $url    An URL
     * @param integer $time   The time of the data
     *
     * @return Boolean Write operation successful
     */
    function write($token, $parent, $data, $ip, $url, $time);

    /**
     * Purges all data from the database.
     */
    function purge();
}
