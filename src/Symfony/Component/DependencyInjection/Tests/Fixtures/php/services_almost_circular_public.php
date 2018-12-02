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
class Symfony_DI_PhpDumper_Test_Almost_Circular_Public extends Container
{
    private $parameters;
    private $targetDirs = array();

    public function __construct()
    {
        $this->services = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'bar3' => 'getBar3Service',
            'bar5' => 'getBar5Service',
            'bar6' => 'getBar6Service',
            'baz6' => 'getBaz6Service',
            'connection' => 'getConnectionService',
            'connection2' => 'getConnection2Service',
            'dispatcher' => 'getDispatcherService',
            'dispatcher2' => 'getDispatcher2Service',
            'foo' => 'getFooService',
            'foo2' => 'getFoo2Service',
            'foo4' => 'getFoo4Service',
            'foo5' => 'getFoo5Service',
            'foo6' => 'getFoo6Service',
            'foobar' => 'getFoobarService',
            'foobar2' => 'getFoobar2Service',
            'foobar3' => 'getFoobar3Service',
            'foobar4' => 'getFoobar4Service',
            'level2' => 'getLevel2Service',
            'level3' => 'getLevel3Service',
            'level4' => 'getLevel4Service',
            'level5' => 'getLevel5Service',
            'level6' => 'getLevel6Service',
            'logger' => 'getLoggerService',
            'manager' => 'getManagerService',
            'manager2' => 'getManager2Service',
            'multiuse1' => 'getMultiuse1Service',
            'root' => 'getRootService',
            'subscriber' => 'getSubscriberService',
        );
        $this->privates = array(
            'bar6' => true,
            'level2' => true,
            'level3' => true,
            'level4' => true,
            'level5' => true,
            'level6' => true,
            'multiuse1' => true,
        );

        $this->aliases = array();
    }

    public function getRemovedIds()
    {
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'bar2' => true,
            'bar6' => true,
            'config' => true,
            'config2' => true,
            'level2' => true,
            'level3' => true,
            'level4' => true,
            'level5' => true,
            'level6' => true,
            'logger2' => true,
            'multiuse1' => true,
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
     * Gets the public 'bar' shared service.
     *
     * @return \BarCircular
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \BarCircular();

        $instance->addFoobar(${($_ = isset($this->services['foobar']) ? $this->services['foobar'] : $this->getFoobarService()) && false ?: '_'});

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

        $a = ${($_ = isset($this->services['foobar3']) ? $this->services['foobar3'] : ($this->services['foobar3'] = new \FoobarCircular())) && false ?: '_'};

        $instance->addFoobar($a, $a);

        return $instance;
    }

    /**
     * Gets the public 'bar5' shared service.
     *
     * @return \stdClass
     */
    protected function getBar5Service()
    {
        $a = ${($_ = isset($this->services['foo5']) ? $this->services['foo5'] : $this->getFoo5Service()) && false ?: '_'};

        if (isset($this->services['bar5'])) {
            return $this->services['bar5'];
        }

        $this->services['bar5'] = $instance = new \stdClass($a);

        $instance->foo = $a;

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
        $a = ${($_ = isset($this->services['dispatcher']) ? $this->services['dispatcher'] : $this->getDispatcherService()) && false ?: '_'};

        if (isset($this->services['connection'])) {
            return $this->services['connection'];
        }
        $b = new \stdClass();

        $this->services['connection'] = $instance = new \stdClass($a, $b);

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
        $a = ${($_ = isset($this->services['dispatcher2']) ? $this->services['dispatcher2'] : $this->getDispatcher2Service()) && false ?: '_'};

        if (isset($this->services['connection2'])) {
            return $this->services['connection2'];
        }
        $b = new \stdClass();

        $this->services['connection2'] = $instance = new \stdClass($a, $b);

        $c = new \stdClass($instance);
        $c->handler2 = new \stdClass(${($_ = isset($this->services['manager2']) ? $this->services['manager2'] : $this->getManager2Service()) && false ?: '_'});

        $b->logger2 = $c;

        return $instance;
    }

    /**
     * Gets the public 'dispatcher' shared service.
     *
     * @return \stdClass
     */
    protected function getDispatcherService($lazyLoad = true)
    {
        $this->services['dispatcher'] = $instance = new \stdClass();

        $instance->subscriber = ${($_ = isset($this->services['subscriber']) ? $this->services['subscriber'] : $this->getSubscriberService()) && false ?: '_'};

        return $instance;
    }

    /**
     * Gets the public 'dispatcher2' shared service.
     *
     * @return \stdClass
     */
    protected function getDispatcher2Service($lazyLoad = true)
    {
        $this->services['dispatcher2'] = $instance = new \stdClass();

        $instance->subscriber2 = new \stdClass(${($_ = isset($this->services['manager2']) ? $this->services['manager2'] : $this->getManager2Service()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \FooCircular
     */
    protected function getFooService()
    {
        $a = ${($_ = isset($this->services['bar']) ? $this->services['bar'] : $this->getBarService()) && false ?: '_'};

        if (isset($this->services['foo'])) {
            return $this->services['foo'];
        }

        return $this->services['foo'] = new \FooCircular($a);
    }

    /**
     * Gets the public 'foo2' shared service.
     *
     * @return \FooCircular
     */
    protected function getFoo2Service()
    {
        $a = new \BarCircular();

        $this->services['foo2'] = $instance = new \FooCircular($a);

        $a->addFoobar(${($_ = isset($this->services['foobar2']) ? $this->services['foobar2'] : $this->getFoobar2Service()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'foo4' service.
     *
     * @return \stdClass
     */
    protected function getFoo4Service()
    {
        $instance = new \stdClass();

        $instance->foobar = ${($_ = isset($this->services['foobar4']) ? $this->services['foobar4'] : $this->getFoobar4Service()) && false ?: '_'};

        return $instance;
    }

    /**
     * Gets the public 'foo5' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo5Service()
    {
        $this->services['foo5'] = $instance = new \stdClass();

        $instance->bar = ${($_ = isset($this->services['bar5']) ? $this->services['bar5'] : $this->getBar5Service()) && false ?: '_'};

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
     * Gets the public 'foobar' shared service.
     *
     * @return \FoobarCircular
     */
    protected function getFoobarService()
    {
        $a = ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->getFooService()) && false ?: '_'};

        if (isset($this->services['foobar'])) {
            return $this->services['foobar'];
        }

        return $this->services['foobar'] = new \FoobarCircular($a);
    }

    /**
     * Gets the public 'foobar2' shared service.
     *
     * @return \FoobarCircular
     */
    protected function getFoobar2Service()
    {
        $a = ${($_ = isset($this->services['foo2']) ? $this->services['foo2'] : $this->getFoo2Service()) && false ?: '_'};

        if (isset($this->services['foobar2'])) {
            return $this->services['foobar2'];
        }

        return $this->services['foobar2'] = new \FoobarCircular($a);
    }

    /**
     * Gets the public 'foobar3' shared service.
     *
     * @return \FoobarCircular
     */
    protected function getFoobar3Service()
    {
        return $this->services['foobar3'] = new \FoobarCircular();
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
     * Gets the public 'root' shared service.
     *
     * @return \stdClass
     */
    protected function getRootService()
    {
        return $this->services['root'] = new \stdClass(${($_ = isset($this->services['level2']) ? $this->services['level2'] : $this->getLevel2Service()) && false ?: '_'}, ${($_ = isset($this->services['multiuse1']) ? $this->services['multiuse1'] : ($this->services['multiuse1'] = new \stdClass())) && false ?: '_'});
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

    /**
     * Gets the private 'level2' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls
     */
    protected function getLevel2Service()
    {
        $this->services['level2'] = $instance = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $instance->call(${($_ = isset($this->services['level3']) ? $this->services['level3'] : $this->getLevel3Service()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the private 'level3' shared service.
     *
     * @return \stdClass
     */
    protected function getLevel3Service()
    {
        return $this->services['level3'] = new \stdClass(${($_ = isset($this->services['level4']) ? $this->services['level4'] : $this->getLevel4Service()) && false ?: '_'});
    }

    /**
     * Gets the private 'level4' shared service.
     *
     * @return \stdClass
     */
    protected function getLevel4Service()
    {
        return $this->services['level4'] = new \stdClass(${($_ = isset($this->services['multiuse1']) ? $this->services['multiuse1'] : ($this->services['multiuse1'] = new \stdClass())) && false ?: '_'}, ${($_ = isset($this->services['level5']) ? $this->services['level5'] : $this->getLevel5Service()) && false ?: '_'});
    }

    /**
     * Gets the private 'level5' shared service.
     *
     * @return \stdClass
     */
    protected function getLevel5Service()
    {
        $a = ${($_ = isset($this->services['level6']) ? $this->services['level6'] : $this->getLevel6Service()) && false ?: '_'};

        if (isset($this->services['level5'])) {
            return $this->services['level5'];
        }

        return $this->services['level5'] = new \stdClass($a);
    }

    /**
     * Gets the private 'level6' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls
     */
    protected function getLevel6Service()
    {
        $this->services['level6'] = $instance = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $instance->call(${($_ = isset($this->services['level5']) ? $this->services['level5'] : $this->getLevel5Service()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the private 'multiuse1' shared service.
     *
     * @return \stdClass
     */
    protected function getMultiuse1Service()
    {
        return $this->services['multiuse1'] = new \stdClass();
    }
}
