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

use Symfony\Component\HttpKernel\DependencyInjection\AddCacheWarmerPass as BaseAddCacheWarmerPass;

@trigger_error('The '.AddCacheWarmerPass::class.' class is deprecated since version 3.3 and will be removed in 4.0. Use the '.BaseAddCacheWarmerPass::class.' class instead.', E_USER_DEPRECATED);

/**
 * Registers the cache warmers.
 *
 * @deprecated This class is deprecated since 3.3, and will be removed in 4.0. Use Symfony\Component\HttpKernel\DependencyInjection\AddCacheWarmerPass instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddCacheWarmerPass extends BaseAddCacheWarmerPass
{
    public function __construct()
    {
        parent::__construct('cache_warmer', 'kernel.cache_warmer');
    }
}
