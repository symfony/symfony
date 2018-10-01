<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Almost_Circular_Private extends Container
{
    private $parameters;
    private $targetDirs = array();

    public function __construct()
    {
        $this->services = array();
        $this->methodMap = array(
            'bar2' => 'getBar2Service',
            'bar3' => 'getBar3Service',
            'bar6' => 'getBar6Service',
            'baz6' => 'getBaz6Service',
            'connection' => 'getConnectionService',
            'connection2' => 'getConnection2Service',
            'foo' => 'getFooService',
            'foo2' => 'getFoo2Service',
            'foo5' => 'getFoo5Service',
            'foo6' => 'getFoo6Service',
            'foobar4' => 'getFoobar4Service',
            'logger' => 'getLoggerService',
            'manager' => 'getManagerService',
            'manager2' => 'getManager2Service',
            'subscriber' => 'getSubscriberService',
        );
        $this->privates = array(
            'bar6' => true,
        );

        $this->aliases = array();
    }

    public function getRemovedIds()
    {
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'bar' => true,
            'bar5' => true,
            'bar6' => true,
            'config' => true,
            'config2' => true,
            'dispatcher' => true,
            'dispatcher2' => true,
            'foo4' => true,
            'foobar' => true,
            'foobar2' => true,
            'foobar3' => true,
            'logger2' => true,
            'subscriber2' => true,
        );
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the public 'bar2' shared service.
     *
     * @return \BarCircular
     */
    protected function getBar2Service()
    {
        $this->services['bar2'] = $instance = new \BarCircular();

        $instance->addFoobar(new \FoobarCircular(${($_ = isset($this->services['foo2']) ? $this->services['foo2'] : $this->getFoo2Service()) && false ?: '_'}));

        return $instance;
    }

    /**
     * Gets the public 'bar3' shared service.
     *
     * @return \BarCircular
     */
    protected function getBar3Service()
    {
        $this->services['bar3'] = $instance = new \BarCircular();

        $a = new \FoobarCircular();

        $instance->addFoobar($a, $a);

        return $instance;
    }

    /**
     * Gets the public 'baz6' shared service.
     *
     * @return \stdClass
     */
    protected function getBaz6Service()
    {
        $this->services['baz6'] = $instance = new \stdClass();

        $instance->bar6 = ${($_ = isset($this->services['bar6']) ? $this->services['bar6'] : $this->getBar6Service()) && false ?: '_'};

        return $instance;
    }

    /**
     * Gets the public 'connection' shared service.
     *
     * @return \stdClass
     */
    protected function getConnectionService()
    {
        $a = new \stdClass();

        $b = new \stdClass();

        $this->services['connection'] = $instance = new \stdClass($a, $b);

        $a->subscriber = ${($_ = isset($this->services['subscriber']) ? $this->services['subscriber'] : $this->getSubscriberService()) && false ?: '_'};

        $b->logger = ${($_ = isset($this->services['logger']) ? $this->services['logger'] : $this->getLoggerService()) && false ?: '_'};

        return $instance;
    }

    /**
     * Gets the public 'connection2' shared service.
     *
     * @return \stdClass
     */
    protected function getConnection2Service()
    {
        $a = new \stdClass();

        $c = new \stdClass();

        $this->services['connection2'] = $instance = new \stdClass($a, $c);

        $b = ${($_ = isset($this->services['manager2']) ? $this->services['manager2'] : $this->getManager2Service()) && false ?: '_'};

        $a->subscriber2 = new \stdClass($b);

        $d = new \stdClass($instance);

        $d->handler2 = new \stdClass($b);

        $c->logger2 = $d;

        return $instance;
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \FooCircular
     */
    protected function getFooService()
    {
        $a = new \BarCircular();

        $this->services['foo'] = $instance = new \FooCircular($a);

        $a->addFoobar(new \FoobarCircular($instance));

        return $instance;
    }

    /**
     * Gets the public 'foo2' shared service.
     *
     * @return \FooCircular
     */
    protected function getFoo2Service()
    {
        $a = ${($_ = isset($this->services['bar2']) ? $this->services['bar2'] : $this->getBar2Service()) && false ?: '_'};

        if (isset($this->services['foo2'])) {
            return $this->services['foo2'];
        }

        return $this->services['foo2'] = new \FooCircular($a);
    }

    /**
     * Gets the public 'foo5' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo5Service()
    {
        $this->services['foo5'] = $instance = new \stdClass();

        $a = new \stdClass($instance);

        $a->foo = $instance;

        $instance->bar = $a;

        return $instance;
    }

    /**
     * Gets the public 'foo6' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo6Service()
    {
        $this->services['foo6'] = $instance = new \stdClass();

        $instance->bar6 = ${($_ = isset($this->services['bar6']) ? $this->services['bar6'] : $this->getBar6Service()) && false ?: '_'};

        return $instance;
    }

    /**
     * Gets the public 'foobar4' shared service.
     *
     * @return \stdClass
     */
    protected function getFoobar4Service()
    {
        $a = new \stdClass();

        $this->services['foobar4'] = $instance = new \stdClass($a);

        $a->foobar = $instance;

        return $instance;
    }

    /**
     * Gets the public 'logger' shared service.
     *
     * @return \stdClass
     */
    protected function getLoggerService()
    {
        $a = ${($_ = isset($this->services['connection']) ? $this->services['connection'] : $this->getConnectionService()) && false ?: '_'};

        if (isset($this->services['logger'])) {
            return $this->services['logger'];
        }

        $this->services['logger'] = $instance = new \stdClass($a);

        $instance->handler = new \stdClass(${($_ = isset($this->services['manager']) ? $this->services['manager'] : $this->getManagerService()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'manager' shared service.
     *
     * @return \stdClass
     */
    protected function getManagerService()
    {
        $a = ${($_ = isset($this->services['connection']) ? $this->services['connection'] : $this->getConnectionService()) && false ?: '_'};

        if (isset($this->services['manager'])) {
            return $this->services['manager'];
        }

        return $this->services['manager'] = new \stdClass($a);
    }

    /**
     * Gets the public 'manager2' shared service.
     *
     * @return \stdClass
     */
    protected function getManager2Service()
    {
        $a = ${($_ = isset($this->services['connection2']) ? $this->services['connection2'] : $this->getConnection2Service()) && false ?: '_'};

        if (isset($this->services['manager2'])) {
            return $this->services['manager2'];
        }

        return $this->services['manager2'] = new \stdClass($a);
    }

    /**
     * Gets the public 'subscriber' shared service.
     *
     * @return \stdClass
     */
    protected function getSubscriberService()
    {
        $a = ${($_ = isset($this->services['manager']) ? $this->services['manager'] : $this->getManagerService()) && false ?: '_'};

        if (isset($this->services['subscriber'])) {
            return $this->services['subscriber'];
        }

        return $this->services['subscriber'] = new \stdClass($a);
    }

    /**
     * Gets the private 'bar6' shared service.
     *
     * @return \stdClass
     */
    protected function getBar6Service()
    {
        $a = ${($_ = isset($this->services['foo6']) ? $this->services['foo6'] : $this->getFoo6Service()) && false ?: '_'};

        if (isset($this->services['bar6'])) {
            return $this->services['bar6'];
        }

        return $this->services['bar6'] = new \stdClass($a);
    }
}
