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

use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass as BaseCacheCollectorPass;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.2, use "%s" instead.', CacheCollectorPass::class, BaseCacheCollectorPass::class), \E_USER_DEPRECATED);

/**
 * Inject a data collector to all the cache services to be able to get detailed statistics.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @deprecated since Symfony 4.2, use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass instead.
 */
class CacheCollectorPass extends BaseCacheCollectorPass
{
}
