<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use Symfony\Bundle\WebProfilerBundle\DependencyInjection\Compiler\ProfilerPass instead.', ProfilerPass::class), E_USER_DEPRECATED);

use Symfony\Bundle\WebProfilerBundle\DependencyInjection\Compiler\ProfilerPass as BaseProfilerPass;

/**
 * Adds tagged data_collector services to profiler service.
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use {@link BaseProfilerPass} instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerPass extends BaseProfilerPass
{
}
