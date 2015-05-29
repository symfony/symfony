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

use Symfony\Component\Profiler\Storage\MemcacheProfilerStorage as BaseMemcacheProfilerStorage;

/**
 * Memcache Profiler Storage.
 *
 * @author Andrej Hudec <pulzarraider@gmail.com>
 * 
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\Profiler\Storage\MemcacheProfilerStorage instead.
 */
class MemcacheProfilerStorage extends BaseMemcacheProfilerStorage
{
}
