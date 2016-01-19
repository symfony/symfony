<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Mapping\Cache\PsrCache;

class PsrCacheTest extends AbstractCacheTest
{
    /**
     * {@inheritdoc}
     */
    protected function getCache()
    {
        return new PsrCache(new ArrayAdapter());
    }
}
