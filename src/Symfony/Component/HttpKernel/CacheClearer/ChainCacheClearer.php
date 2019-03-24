<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheClearer;

use Symfony\Component\Kernel\CacheClearer\InternalChainCacheClearer;

/**
 * ChainCacheClearer.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 *
 * @final
 *
 * TODO Trigger class deprecation on version 5.1
 */
class ChainCacheClearer extends InternalChainCacheClearer implements CacheClearerInterface
{
}
