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
use Symfony\Component\VarExporter\LazyProxyTrait;
use Symfony\Component\VarExporter\ProxyHelper;

class RedisProxiesTest extends TestCase
{
    /**
     * @requires extension redis < 6
     *
     * @testWith ["Redis"]
     *           ["RedisCluster"]
     */
    public function testRedis5Proxy($class)
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2)."/Traits/{$class}5Proxy.php");
        $proxy = substr($proxy, 0, 4 + strpos($proxy, '[];'));
        $methods = [];

        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name)) {
                continue;
            }
            $return = $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                {
                    {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        $this->assertStringEqualsFile(\dirname(__DIR__, 2)."/Traits/{$class}5Proxy.php", $proxy);
    }

    /**
     * @requires extension relay
     * @requires PHP 8.2
     */
    public function testRelayProxy()
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2).'/Traits/RelayProxy.php');
        $proxy = substr($proxy, 0, 4 + strpos($proxy, '[];'));
        $methods = [];

        foreach ((new \ReflectionClass(Relay::class))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name) || $method->isStatic()) {
                continue;
            }
            $return = $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                {
                    {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        $this->assertStringEqualsFile(\dirname(__DIR__, 2).'/Traits/RelayProxy.php', $proxy);
    }

    /**
     * @requires extension openssl
     *
     * @testWith ["Redis", "redis"]
     *           ["RedisCluster", "redis_cluster"]
     */
    public function testRedis6Proxy($class, $stub)
    {
        if (version_compare(phpversion('redis'), '6.0.2', '>')) {
            $stub = file_get_contents("https://raw.githubusercontent.com/phpredis/phpredis/develop/{$stub}.stub.php");
        } else {
            $stub = file_get_contents("https://raw.githubusercontent.com/phpredis/phpredis/6.0.2/{$stub}.stub.php");
        }

        $stub = preg_replace('/^class /m', 'return; \0', $stub);
        $stub = preg_replace('/^return; class ([a-zA-Z]++)/m', 'interface \1StubInterface', $stub, 1);
        $stub = preg_replace('/^    public const .*/m', '', $stub);
        eval(substr($stub, 5));

        $this->assertEquals(self::dumpMethods(new \ReflectionClass($class.'StubInterface')), self::dumpMethods(new \ReflectionClass(sprintf('Symfony\Component\Cache\Traits\%s6Proxy', $class))));
    }

    private static function dumpMethods(\ReflectionClass $class): string
    {
        $methods = [];

        foreach ($class->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name)) {
                continue;
            }

            $return = $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $signature = ProxyHelper::exportSignature($method, false, $args);
            $methods[] = "\n    ".str_replace('timeout = 0.0', 'timeout = 0', $signature)."\n".<<<EOPHP
                {
                    {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                }

            EOPHP;
        }

        usort($methods, 'strnatcmp');

        return implode("\n", $methods);
    }
}
