<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

use Symfony\Component\Kernel\CacheWarmer\WarmableInterface as BaseWarmableInterface;

/**
 * Interface for classes that support warming their cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * TODO Trigger class deprecation on version 5.1.
 */
interface WarmableInterface extends BaseWarmableInterface
{
}
