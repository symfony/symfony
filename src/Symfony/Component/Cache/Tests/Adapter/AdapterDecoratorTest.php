<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Cache\IntegrationTests\CachePoolTest;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AdapterDecoratorTest extends CachePoolTest
{
    public function createCachePool()
    {
        return new ApcuAdapter(__CLASS__, 0, new ApcuAdapter());
    }
}
