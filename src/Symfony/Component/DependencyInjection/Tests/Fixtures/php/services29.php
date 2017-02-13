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
 * Symfony_DI_PhpDumper_Test_Overriden_Getters.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Overriden_Getters extends Container
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
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Baz A Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Baz instance
     */
    protected function getBazService()
    {
        return $this->services['baz'] = $this->instantiateProxy(SymfonyProxy_1e2f71108c6e0938cdbc9e38cae3dcb5::class, array(), true);
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Foo instance
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new SymfonyProxy_4a8df73e139322e3fa994cdec2f5b203($this);
    }

    private function instantiateProxy($class, $args, $useConstructor)
    {
        static $reflectionCache;

        if (null === $r = &$reflectionCache[$class]) {
            $r[0] = new \ReflectionClass($class);
            $r[1] = $r[0]->getProperty('container6HqvH3fsTTC6dr66HyT2Jw');
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

class SymfonyProxy_1e2f71108c6e0938cdbc9e38cae3dcb5 extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Baz implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $container6HqvH3fsTTC6dr66HyT2Jw;
    private $getters6HqvH3fsTTC6dr66HyT2Jw;

    protected function getBaz()
    {
        return 'baz';
    }
}

class SymfonyProxy_4a8df73e139322e3fa994cdec2f5b203 extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Foo implements \Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface
{
    private $container6HqvH3fsTTC6dr66HyT2Jw;
    private $getters6HqvH3fsTTC6dr66HyT2Jw;

    public function __construct($container6HqvH3fsTTC6dr66HyT2Jw)
    {
        $this->container6HqvH3fsTTC6dr66HyT2Jw = $container6HqvH3fsTTC6dr66HyT2Jw;
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
        if (null === $g = &$this->getters6HqvH3fsTTC6dr66HyT2Jw[__FUNCTION__]) {
            $g = \Closure::bind(function () { return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}; }, $this->container6HqvH3fsTTC6dr66HyT2Jw, $this->container6HqvH3fsTTC6dr66HyT2Jw);
        }

        return $g();
    }

    public function getInvalid()
    {
        if (null === $g = &$this->getters6HqvH3fsTTC6dr66HyT2Jw[__FUNCTION__]) {
            $g = \Closure::bind(function () { return array(0 => $this->get('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE)); }, $this->container6HqvH3fsTTC6dr66HyT2Jw, $this->container6HqvH3fsTTC6dr66HyT2Jw);
        }

        if ($this->container6HqvH3fsTTC6dr66HyT2Jw->has('bar')) {
            return $g();
        }

        return parent::getInvalid();
    }
}
