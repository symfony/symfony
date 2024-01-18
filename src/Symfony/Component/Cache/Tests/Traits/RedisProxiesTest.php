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
        $proxy = file_get_contents(\dirname(__DIR__, 2)."/Traits/{$class}6Proxy.php");
        $proxy = substr($proxy, 0, strpos($proxy, 'if '));

        $proxy .= "if (version_compare(phpversion('redis'), '6.0.2', '>')) {\n";
        $proxy .= self::dumpMethodsFromStub($class, $stub, 'develop');
        $proxy .= "\n} else {\n";
        $proxy .= self::dumpMethodsFromStub($class, $stub, '6.0.2');
        $proxy .= "\n}\n";

        $this->assertStringEqualsFile(\dirname(__DIR__, 2)."/Traits/{$class}6Proxy.php", $proxy);
    }

    private static function dumpMethodsFromStub(string $class, string $stub, string $version): string
    {
        $url = sprintf('https://raw.githubusercontent.com/phpredis/phpredis/%s/%s.stub.php', $version, $stub);
        $namespace = sprintf('%s\V%s', __NAMESPACE__, str_replace('.', '', $version));

        $stub = sprintf("<?php\nnamespace %s;\n", $namespace);
        $stub .= substr(file_get_contents($url), 5);
        $stub = preg_replace('/^class /m', 'return; \0', $stub);
        $stub = preg_replace('/^return; class ([a-zA-Z]++)/m', 'interface \1StubInterface', $stub, 1);
        $stub = preg_replace('/^    public const .*/m', '', $stub);
        eval(substr($stub, 5));
        $r = new \ReflectionClass(sprintf('%s\%sStubInterface', $namespace, $class));

        $methods = [];

        foreach ($r->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(LazyProxyTrait::class, $method->name)) {
                continue;
            }
            $return = $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[] = "\n        ".str_replace('timeout = 0.0', 'timeout = 0', str_replace($namespace.'\\', '', ProxyHelper::exportSignature($method, false, $args)))."\n".<<<EOPHP
                    {
                        {$return}(\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                    }

            EOPHP;
        }

        $proxy = <<<EOPHP
                /**
                 * @internal
                 */
                class {$class}6Proxy extends \\{$class} implements ResetInterface, LazyObjectInterface
                {
                    use LazyProxyTrait {
                        resetLazyObject as reset;
                    }

                    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            EOPHP;

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods);

        return $proxy.'    }';
    }
}
