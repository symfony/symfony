<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use PHPUnit\Framework\Attributes\Group;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;

#[Group('time-sensitive')]
class Psr16CacheWithExternalAdapter extends SimpleCacheTest
{
    protected function setUp(): void
    {
        $this->skippedTests['testSetTtl'] =
        $this->skippedTests['testSetMultipleTtl'] = 'The ExternalAdapter test class does not support TTLs.';
    }

    public function createSimpleCache(): CacheInterface
    {
        return new Psr16Cache(new ExternalAdapter());
    }
}
