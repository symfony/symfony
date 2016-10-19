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

use Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass as BaseAddCacheClearerPass;

@trigger_error('The '.AddCacheClearerPass::class.' class is deprecated since version 3.3 and will be removed in 4.0. Use the '.BaseAddCacheClearerPass::class.' class instead.', E_USER_DEPRECATED);

/**
 * Registers the cache clearers.
 *
 * @deprecated This class is deprecated since 3.3, and will be removed in 4.0. Use Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass instead.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 */
class AddCacheClearerPass extends BaseAddCacheClearerPass
{
}
