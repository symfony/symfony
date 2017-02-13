<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * Symfony_DI_PhpDumper_Test_Overriden_Getters_With_Constructor.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Overriden_Getters_With_Constructor extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->services = array();
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
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Baz A Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Baz instance
     */
    protected function getBazService()
    {
        return $this->services['baz'] = $this->instantiateProxy(SymfonyProxy_f0afdd0cd14cc92319c3f5d20cec315a::class, array(), true);
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Foo instance
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new SymfonyProxy_4fb8f9a44021ab78702917f65fade566($this);
    }

    private function instantiateProxy($class, $args, $useConstructor)
    {
        static $reflectionCache;

        if (null === $r = &$reflectionCache[$class]) {
            $r[0] = new \ReflectionClass($class);
            $r[1] = $r[0]->getProperty('containerg3aCmsigw5jaB68sqMSEQQ');
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

class SymfonyProxy_f0afdd0cd14cc92319c3f5d20cec315a extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Baz implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $containerg3aCmsigw5jaB68sqMSEQQ;
    private $gettersg3aCmsigw5jaB68sqMSEQQ;

    protected function getBaz()
    {
        return 'baz';
    }
}

class SymfonyProxy_4fb8f9a44021ab78702917f65fade566 extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Foo implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $containerg3aCmsigw5jaB68sqMSEQQ;
    private $gettersg3aCmsigw5jaB68sqMSEQQ;

    public function __construct($containerg3aCmsigw5jaB68sqMSEQQ, $bar = 'bar')
    {
        $this->containerg3aCmsigw5jaB68sqMSEQQ = $containerg3aCmsigw5jaB68sqMSEQQ;
        parent::__construct($bar);
    }

    public function getPublic()
    {
        return 'public';
    }

    protected function getProtected()
    {
        return 'protected';
    }

    public function getSelf()
    {
        if (null === $g = &$this->gettersg3aCmsigw5jaB68sqMSEQQ[__FUNCTION__]) {
            $g = \Closure::bind(function () { return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}; }, $this->containerg3aCmsigw5jaB68sqMSEQQ, $this->containerg3aCmsigw5jaB68sqMSEQQ);
        }

        return $g();
    }

    public function getInvalid()
    {
        if (null === $g = &$this->gettersg3aCmsigw5jaB68sqMSEQQ[__FUNCTION__]) {
            $g = \Closure::bind(function () { return array(0 => $this->get('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE)); }, $this->containerg3aCmsigw5jaB68sqMSEQQ, $this->containerg3aCmsigw5jaB68sqMSEQQ);
        }

        if ($this->containerg3aCmsigw5jaB68sqMSEQQ->has('bar')) {
            return $g();
        }

        return parent::getInvalid();
    }
}
