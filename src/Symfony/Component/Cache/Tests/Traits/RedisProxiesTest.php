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

use PHPUnit\Framework\TestCase;
use Relay\Relay;
use Symfony\Component\Cache\Traits\Redis6Proxy;
use Symfony\Component\Cache\Traits\RedisCluster6Proxy;
use Symfony\Component\Cache\Traits\RelayProxy;
use Symfony\Component\VarExporter\LazyProxyTrait;
use Symfony\Component\VarExporter\ProxyHelper;

class RedisProxiesTest extends TestCase
{
    /**
     * @requires extension redis
     */
    public function testRedisLazyProxyForNewCommands()
    {
        if (!method_exists(\Redis::class, '_digest')) {
            $this->markTestSkipped('The Redis extension does not support digest commands.');
        }

        $calls = 0;
        $proxy = Redis6Proxy::createLazyProxy(static function () use (&$calls) {
            ++$calls;

            return new class extends \Redis {
                public function __construct()
                {
                }

                public function _digest($value): string
                {
                    return 'digest:'.$value;
                }
            };
        });

        $this->assertSame(0, $calls);
        $this->assertSame('digest:first', $proxy->_digest('first'));
        $this->assertSame('digest:second', $proxy->_digest('second'));
        $this->assertSame(1, $calls);
    }

    /**
     * @requires extension redis
     */
    public function testRedisClusterLazyProxyForNewCommands()
    {
        if (!method_exists(\RedisCluster::class, '_digest')) {
            $this->markTestSkipped('The Redis extension does not support digest commands.');
        }

        $calls = 0;
        $proxy = RedisCluster6Proxy::createLazyProxy(static function () use (&$calls) {
            ++$calls;

            return new class extends \RedisCluster {
                public function __construct()
                {
                }

                public function _digest($value): string
                {
                    return 'digest:'.$value;
                }
            };
        });

        $this->assertSame(0, $calls);
        $this->assertSame('digest:first', $proxy->_digest('first'));
        $this->assertSame('digest:second', $proxy->_digest('second'));
        $this->assertSame(1, $calls);
    }

    /**
     * @requires extension redis
     *
     * @testWith ["Redis"]
     *           ["RedisCluster"]
     */
    public function testRedisProxy($class)
    {
        $version = version_compare(phpversion('redis'), '6', '>') ? '6' : '5';
        $proxy = file_get_contents(\dirname(__DIR__, 2)."/Traits/{$class}{$version}Proxy.php");
        $proxy = substr($proxy, 0, 4 + strpos($proxy, '[];'));
        $expected = substr($proxy, 0, 4 + strpos($proxy, '[];'));
        $methods = [];

        foreach ((new \ReflectionClass(\sprintf('Symfony\Component\Cache\Traits\\%s%dProxy', $class, $version)))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name) || $method->isInternal()) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, true, $args)."\n".<<<EOPHP
                    {
                        {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                    }

                EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        $methods = [];

        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            if ('__destruct' === $method->name || 'reset' === $method->name) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                    {
                        {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                    }

                EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $expected .= implode('', $methods)."}\n";

        if (!str_contains($expected, '#[\SensitiveParameter] ')) {
            $proxy = str_replace('#[\SensitiveParameter] ', '', $proxy);
        }

        $this->assertSame($expected, $proxy);
    }

    /**
     * @requires extension relay
     * @requires PHP 8.2
     */
    public function testRelayProxy()
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2).'/Traits/RelayProxy.php');
        $proxy = substr($proxy, 0, 4 + strpos($proxy, '[];'));
        $expectedProxy = $proxy;
        $methods = [];
        $expectedMethods = [];

        foreach ((new \ReflectionClass(RelayProxy::class))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name) || $method->isInternal()) {
                continue;
            }

            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $expectedMethods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, true, $args)."\n".<<<EOPHP
                    {
                        {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                    }

                EOPHP;
        }

        foreach ((new \ReflectionClass(Relay::class))->getMethods() as $method) {
            if ('__destruct' === $method->name || 'reset' === $method->name || $method->isStatic()) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                    {
                        {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                    }

                EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        uksort($expectedMethods, 'strnatcmp');
        $expectedProxy .= implode('', $expectedMethods)."}\n";

        $this->assertEquals($expectedProxy, $proxy);
    }
}
