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

use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass as BaseCachePoolClearerPass;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.2, use "%s" instead.', CachePoolClearerPass::class, BaseCachePoolClearerPass::class), \E_USER_DEPRECATED);

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since version 4.2, use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass instead.
 */
class CachePoolClearerPass extends BaseCachePoolClearerPass
{
}
