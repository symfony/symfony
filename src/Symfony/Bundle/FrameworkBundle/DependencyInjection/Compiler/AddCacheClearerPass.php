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

@trigger_error(sprintf('The %s class is deprecated since version 3.4 and will be removed in 4.0. Use Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass instead.', AddCacheClearerPass::class), E_USER_DEPRECATED);

use Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass as BaseAddCacheClearerPass;

/**
 * Registers the cache clearers.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use {@link BaseAddCacheClearerPass instead}.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 */
class AddCacheClearerPass extends BaseAddCacheClearerPass
{
}
