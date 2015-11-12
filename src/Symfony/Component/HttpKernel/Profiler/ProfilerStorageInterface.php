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

@trigger_error('The '.__NAMESPACE__.'\ProfilerStorageInterface class is deprecated since Symfony 2.8 and will be removed in 3.0. Use Symfony\Component\Profiler\Storage\ProfilerStorageInterface instead.', E_USER_DEPRECATED);

use Symfony\Component\Profiler\Storage\ProfilerStorageInterface as BaseProfilerStorageInterface;

/**
 * ProfilerStorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
 *             Use {@link Symfony\Component\Profiler\Storage\ProfilerStorageInterface} instead.
 */
interface ProfilerStorageInterface extends BaseProfilerStorageInterface
{
    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string   $ip     The IP
     * @param string   $url    The URL
     * @param string   $limit  The maximum number of tokens to return
     * @param string   $method The request method
     * @param int|null $start  The start date to search from
     * @param int|null $end    The end date to search to
     *
     * @return array An array of tokens
     *
     * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
     *             Use {@link Symfony\Component\Profiler\Storage\ProfilerStorageInterface::findBy} instead.
     */
    public function find($ip, $url, $limit, $method, $start = null, $end = null);

    /**
     * @param Profile $profile
     * @return mixed
     */
    public function write(Profile $profile);
}
