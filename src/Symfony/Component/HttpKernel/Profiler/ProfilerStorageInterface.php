<?php

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ProfilerStorageInterface.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
     * Reads data associated with the given token.
     *
     * The method returns false if the token does not exists in the storage.
     *
     * @param string $token A token
     *
     * @return DataCollectorInterface[] An array of DataCollectorInterface instance
     */
    function read($token);

    /**
     * Reads data associated with the given token.
     *
     * @param string                   $token A token
     * @param DataCollectorInterface[] $collectors An array of DataCollectorInterface instances
     * @param string                   $ip    An IP
     * @param string                   $url   An URL
     * @param integer                  $time  The time of the data
     */
    function write($token, $collectors, $ip, $url, $time);
}
