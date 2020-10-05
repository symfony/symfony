<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\PruneableInterface;

abstract class PrunableCache implements CacheInterface, PruneableInterface
{
}
