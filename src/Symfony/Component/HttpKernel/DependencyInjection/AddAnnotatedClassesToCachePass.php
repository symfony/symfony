<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\Kernel\DependencyInjection\AddAnnotatedClassesToCachePass as BaseAddAnnotatedClassesToCachePass;

/**
 * Sets the classes to compile in the cache for the container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * TODO Trigger class deprecation on version 5.1.
 */
class AddAnnotatedClassesToCachePass extends BaseAddAnnotatedClassesToCachePass
{
}
