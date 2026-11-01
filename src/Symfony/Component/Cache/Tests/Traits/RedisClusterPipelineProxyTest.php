<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Traits\RedisClusterProxy;

#[RequiresPhpExtension('redis')]
class RedisClusterPipelineProxyTest extends TestCase
{
    public function testPipelineInitializesTheLazyConnection()
    {
        if (!method_exists(\RedisCluster::class, 'pipeline')) {
            $this->markTestSkipped('The Redis extension does not support cluster pipelines.');
        }

        $redis = new class extends \RedisCluster {
            public function __construct()
            {
            }

            public function pipeline(): \RedisCluster|bool
            {
                return $this;
            }

            public function exec(): \RedisCluster|array|false
            {
                return $this;
            }
        };
        $calls = 0;
        $proxy = RedisClusterProxy::createLazyProxy(static function () use ($redis, &$calls) {
            ++$calls;

            return $redis;
        });

        $this->assertSame(0, $calls);
        $this->assertSame($redis, $proxy->pipeline());
        $this->assertSame($redis, $proxy->exec());
        $this->assertSame(1, $calls);
    }
}
