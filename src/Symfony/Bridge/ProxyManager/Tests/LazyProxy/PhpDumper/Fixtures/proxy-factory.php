<?php

return new class
{
    public $proxyClass;
    private $privates = [];

    public function getFooService($lazyLoad = true)
    {
        $container = $this;

        if (true === $lazyLoad) {
            return $container->privates['foo'] = $container->createProxy('SunnyInterface_1eff735', static fn () => \SunnyInterface_1eff735::staticProxyConstructor(
                static function (&$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface $proxy) use ($container) {
                    $wrappedInstance = $container->getFooService(false);

                    $proxy->setProxyInitializer(null);

                    return true;
                }
            ));
        }

        return new Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\DummyClass();
    }

    protected function createProxy($class, \Closure $factory)
    {
        $this->proxyClass = $class;

        return $factory();
    }
};
