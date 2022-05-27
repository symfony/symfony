<?php

namespace Symfony\Component\DependencyInjection\MemoizeProxy;

use ProxyManager\Configuration;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\ProxyManager\AccessInterceptor\Instanciator\AccessInterceptorValueHolderFactory;

/**
 * Memoize a service by creating a ProxyManager\Proxy\AccessInterceptorValueHolder
 */
final class MemoizeFactory
{
    readonly private AccessInterceptorValueHolderFactory $factory;

    public function __construct(string $cacheDirectory)
    {
        if (!is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0777, true);
        }

        $config = new Configuration();
        $config->setGeneratorStrategy(new FileWriterGeneratorStrategy(new FileLocator($cacheDirectory)));
        $config->setProxiesTargetDir($cacheDirectory);

        $this->factory = new AccessInterceptorValueHolderFactory($config);
    }

    /**
     * @param object $service Service to memoize
     * @param array<array{CacheItemPoolInterface, KeyGeneratorInterface, int}> $methods
     */
    public function __invoke(object $service, array $methods): AccessInterceptorValueHolderInterface
    {
        $proxy = $this->factory->createProxy($service);
        foreach ($methods as $name => [$cache, $key, $ttl]) {
            $interceptor = new Interceptor($cache, $key, $ttl);
            $proxy->setMethodPrefixInterceptor($name, $interceptor->getPrefixInterceptor(...));
            $proxy->setMethodSuffixInterceptor($name, $interceptor->getSuffixInterceptor(...));
        }

        return $proxy;
    }
}
