<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Symfony_DI_PhpDumper_Test_Tail_Methods.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Tail_Methods extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->services = array();
        $this->normalizedIds = array(
            'psr\\container\\containerinterface' => 'Psr\\Container\\ContainerInterface',
            'symfony\\component\\dependencyinjection\\container' => 'Symfony\\Component\\DependencyInjection\\Container',
            'symfony\\component\\dependencyinjection\\containerinterface' => 'Symfony\\Component\\DependencyInjection\\ContainerInterface',
        );
        $this->methodMap = array(
            'baz' => 'getBazService',
            'foo' => 'getFooService',
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped frozen container.');
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        return true;
    }

    /**
     * Gets the 'baz' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Baz A Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Baz instance
     */
    protected function getBazService()
    {
        return $this->services['baz'] = $this->instantiateProxy(SymfonyProxy_8c73474cb8141efea9d50aa6c4f80fa0::class, array(), true);
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Foo instance
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new SymfonyProxy_9ff17673d1eeac1c1e97759e44f2e76e($this);
    }

    private function instantiateProxy($class, $args, $useConstructor)
    {
        static $reflectionCache;

        if (null === $r = &$reflectionCache[$class]) {
            $r[0] = new \ReflectionClass($class);
            $r[1] = $r[0]->getProperty('containeru1sCxo6vGxZrp0Vla5q50A');
            $r[1]->setAccessible(true);
            $r[2] = $r[0]->getConstructor();
        }
        $service = $useConstructor ? $r[0]->newInstanceWithoutConstructor() : $r[0]->newInstanceArgs($args);
        $r[1]->setValue($service, $this);
        if ($r[2] && $useConstructor) {
            $r[2]->invokeArgs($service, $args);
        }

        return $service;
    }
}

class SymfonyProxy_8c73474cb8141efea9d50aa6c4f80fa0 extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Baz implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $containeru1sCxo6vGxZrp0Vla5q50A;
    private $tailsu1sCxo6vGxZrp0Vla5q50A;

    protected function method1($arg1, $arg2 = null)
    {
        switch (func_num_args()) {
            case 0:
            case 1:
                if (null === $pu1sCxo6vGxZrp0Vla5q50A = &$this->tailsu1sCxo6vGxZrp0Vla5q50A[__FUNCTION__][1]) {
                    $pu1sCxo6vGxZrp0Vla5q50A = \Closure::bind(function () { return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}; }, $this->containeru1sCxo6vGxZrp0Vla5q50A, $this->containeru1sCxo6vGxZrp0Vla5q50A);
                }
                $arg2 = $pu1sCxo6vGxZrp0Vla5q50A();
        }

        return parent::method1($arg1, $arg2);
    }
}

class SymfonyProxy_9ff17673d1eeac1c1e97759e44f2e76e extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Tails\Foo implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $containeru1sCxo6vGxZrp0Vla5q50A;
    private $tailsu1sCxo6vGxZrp0Vla5q50A;

    public function __construct($containeru1sCxo6vGxZrp0Vla5q50A)
    {
        $this->containeru1sCxo6vGxZrp0Vla5q50A = $containeru1sCxo6vGxZrp0Vla5q50A;
    }

    public function method1($arg = null)
    {
        switch (func_num_args()) {
            case 0: $arg = 123;
        }

        return parent::method1($arg);
    }

    public function method0($bar = null)
    {
        switch (func_num_args()) {
            case 0:
                if (null === $pu1sCxo6vGxZrp0Vla5q50A = &$this->tailsu1sCxo6vGxZrp0Vla5q50A[__FUNCTION__][0]) {
                    $pu1sCxo6vGxZrp0Vla5q50A = \Closure::bind(function () { return $this->get('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE); }, $this->containeru1sCxo6vGxZrp0Vla5q50A, $this->containeru1sCxo6vGxZrp0Vla5q50A);
                }
                $bar = $pu1sCxo6vGxZrp0Vla5q50A();
        }

        return parent::method0($bar);
    }
}
