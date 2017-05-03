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

@trigger_error(sprintf('The %s class is deprecated since version 3.4 and will be removed in 4.0. Use Symfony\Component\HttpKernel\DependencyInjection\AddCacheWarmerPass instead.', AddCacheWarmerPass::class), E_USER_DEPRECATED);

use Symfony\Component\HttpKernel\DependencyInjection\AddCacheWarmerPass as BaseAddCacheWarmerPass;

/**
 * Registers the cache warmers.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use {@link BaseAddCacheWarmerPass instead}.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddCacheWarmerPass extends BaseAddCacheWarmerPass
{
}
