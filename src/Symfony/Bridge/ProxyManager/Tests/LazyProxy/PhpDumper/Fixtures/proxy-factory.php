<?php

return new class
{
    public $proxyClass;
    private $privates = array();

    public function getFooService($lazyLoad = true)
    {
        if ($lazyLoad) {
            return $this->privates['foo'] = $this->createProxy('SunnyInterface_1eff735', function () {
                return \SunnyInterface_1eff735::staticProxyConstructor(function (&$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface $proxy) {
                    $wrappedInstance = $this->getFooService(false);

                    $proxy->setProxyInitializer(null);

                    return true;
                });
            });
        }

        return new Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\DummyClass();
    }

    protected function createProxy($class, \Closure $factory)
    {
        $this->proxyClass = $class;

        return $factory();
    }
};
