<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\Profiler\EventListener\HttpProfilerListener;

/**
 * ProfilerListener collects data for the current request by listening to the kernel events.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @deprecated since x.x, to be removed in x.x. Use Symfony\Component\Profiler\EventListener\ProfileListener instead.
 */
class ProfilerListener extends HttpProfilerListener
{

}