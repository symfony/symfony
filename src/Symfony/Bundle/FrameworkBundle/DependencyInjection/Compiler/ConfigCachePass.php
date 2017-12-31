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

use Symfony\Component\Config\DependencyInjection\ConfigCachePass as BaseConfigCachePass;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use tagged iterator arguments instead.', ConfigCachePass::class), E_USER_DEPRECATED);

/**
 * Adds services tagged config_cache.resource_checker to the config_cache_factory service, ordering them by priority.
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use tagged iterator arguments instead.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class ConfigCachePass extends BaseConfigCachePass
{
}
