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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Debug\BufferingLogger;

/**
 * @group time-sensitive
 */
class Psr16AdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testPrune' => 'Psr16adapter just proxies',
    ];

    public function createCachePool($defaultLifetime = 0)
    {
        return new Psr16Adapter(new Psr16Cache(new FilesystemAdapter()), '', $defaultLifetime);
    }

    public function testValidCacheKeyWithNamespace()
    {
        $logger = new BufferingLogger();
        $cache = new Psr16Adapter(new Psr16Cache(new FilesystemAdapter()), 'some_namespace', 0);
        $cache->setLogger($logger);
        $this->assertSame('foo', $cache->get('my_key', function () {
            return 'foo';
        }));
        $logs = $logger->cleanLogs();
        foreach ($logs as $log) {
            if ('warning' === $log[0] || 'error' === $log[0]) {
                $this->fail('An error was triggered while caching key with a namespace: '.$log[1]);
            }
        }
    }
}
