<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final
 */
class Symfony_DI_PhpDumper_Test_Almost_Circular_Public extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar' => 'getBarService',
            'bar3' => 'getBar3Service',
            'bar5' => 'getBar5Service',
            'baz6' => 'getBaz6Service',
            'connection' => 'getConnectionService',
            'connection2' => 'getConnection2Service',
            'connection3' => 'getConnection3Service',
            'connection4' => 'getConnection4Service',
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
            'listener3' => 'getListener3Service',
            'listener4' => 'getListener4Service',
            'logger' => 'getLoggerService',
            'manager' => 'getManagerService',
            'manager2' => 'getManager2Service',
            'manager3' => 'getManager3Service',
            'root' => 'getRootService',
            'subscriber' => 'getSubscriberService',
        ];

        $this->aliases = [];
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function getRemovedIds(): array
    {
        return [
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
            'manager4' => true,
            'multiuse1' => true,
            'subscriber2' => true,
        ];
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \BarCircular
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \BarCircular();

        $instance->addFoobar(($this->services['foobar'] ?? $this->getFoobarService()));

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

        $a = ($this->services['foobar3'] ?? ($this->services['foobar3'] = new \FoobarCircular()));

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
        $a = ($this->services['foo5'] ?? $this->getFoo5Service());

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

        $instance->bar6 = ($this->privates['bar6'] ?? $this->getBar6Service());

        return $instance;
    }

    /**
     * Gets the public 'connection' shared service.
     *
     * @return \stdClass
     */
    protected function getConnectionService()
    {
        $a = ($this->services['dispatcher'] ?? $this->getDispatcherService());

        if (isset($this->services['connection'])) {
            return $this->services['connection'];
        }
        $b = new \stdClass();

        $this->services['connection'] = $instance = new \stdClass($a, $b);

        $b->logger = ($this->services['logger'] ?? $this->getLoggerService());

        return $instance;
    }

    /**
     * Gets the public 'connection2' shared service.
     *
     * @return \stdClass
     */
    protected function getConnection2Service()
    {
        $a = ($this->services['dispatcher2'] ?? $this->getDispatcher2Service());

        if (isset($this->services['connection2'])) {
            return $this->services['connection2'];
        }
        $b = new \stdClass();

        $this->services['connection2'] = $instance = new \stdClass($a, $b);

        $c = new \stdClass($instance);
        $c->handler2 = new \stdClass(($this->services['manager2'] ?? $this->getManager2Service()));

        $b->logger2 = $c;

        return $instance;
    }

    /**
     * Gets the public 'connection3' shared service.
     *
     * @return \stdClass
     */
    protected function getConnection3Service()
    {
        $this->services['connection3'] = $instance = new \stdClass();

        $instance->listener = [0 => ($this->services['listener3'] ?? $this->getListener3Service())];

        return $instance;
    }

    /**
     * Gets the public 'connection4' shared service.
     *
     * @return \stdClass
     */
    protected function getConnection4Service()
    {
        $this->services['connection4'] = $instance = new \stdClass();

        $instance->listener = [0 => ($this->services['listener4'] ?? $this->getListener4Service())];

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

        $instance->subscriber = ($this->services['subscriber'] ?? $this->getSubscriberService());

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

        $instance->subscriber2 = new \stdClass(($this->services['manager2'] ?? $this->getManager2Service()));

        return $instance;
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \FooCircular
     */
    protected function getFooService()
    {
        $a = ($this->services['bar'] ?? $this->getBarService());

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

        $a->addFoobar(($this->services['foobar2'] ?? $this->getFoobar2Service()));

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

        $instance->foobar = ($this->services['foobar4'] ?? $this->getFoobar4Service());

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

        $instance->bar = ($this->services['bar5'] ?? $this->getBar5Service());

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

        $instance->bar6 = ($this->privates['bar6'] ?? $this->getBar6Service());

        return $instance;
    }

    /**
     * Gets the public 'foobar' shared service.
     *
     * @return \FoobarCircular
     */
    protected function getFoobarService()
    {
        $a = ($this->services['foo'] ?? $this->getFooService());

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
        $a = ($this->services['foo2'] ?? $this->getFoo2Service());

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
     * Gets the public 'listener3' shared service.
     *
     * @return \stdClass
     */
    protected function getListener3Service()
    {
        $this->services['listener3'] = $instance = new \stdClass();

        $instance->manager = ($this->services['manager3'] ?? $this->getManager3Service());

        return $instance;
    }

    /**
     * Gets the public 'listener4' shared service.
     *
     * @return \stdClass
     */
    protected function getListener4Service()
    {
        $a = ($this->privates['manager4'] ?? $this->getManager4Service());

        if (isset($this->services['listener4'])) {
            return $this->services['listener4'];
        }

        return $this->services['listener4'] = new \stdClass($a);
    }

    /**
     * Gets the public 'logger' shared service.
     *
     * @return \stdClass
     */
    protected function getLoggerService()
    {
        $a = ($this->services['connection'] ?? $this->getConnectionService());

        if (isset($this->services['logger'])) {
            return $this->services['logger'];
        }

        $this->services['logger'] = $instance = new \stdClass($a);

        $instance->handler = new \stdClass(($this->services['manager'] ?? $this->getManagerService()));

        return $instance;
    }

    /**
     * Gets the public 'manager' shared service.
     *
     * @return \stdClass
     */
    protected function getManagerService()
    {
        $a = ($this->services['connection'] ?? $this->getConnectionService());

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
        $a = ($this->services['connection2'] ?? $this->getConnection2Service());

        if (isset($this->services['manager2'])) {
            return $this->services['manager2'];
        }

        return $this->services['manager2'] = new \stdClass($a);
    }

    /**
     * Gets the public 'manager3' shared service.
     *
     * @return \stdClass
     */
    protected function getManager3Service($lazyLoad = true)
    {
        $a = ($this->services['connection3'] ?? $this->getConnection3Service());

        if (isset($this->services['manager3'])) {
            return $this->services['manager3'];
        }

        return $this->services['manager3'] = new \stdClass($a);
    }

    /**
     * Gets the public 'root' shared service.
     *
     * @return \stdClass
     */
    protected function getRootService()
    {
        $a = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $b = new \stdClass();

        $a->call(new \stdClass(new \stdClass($b, ($this->privates['level5'] ?? $this->getLevel5Service()))));

        return $this->services['root'] = new \stdClass($a, $b);
    }

    /**
     * Gets the public 'subscriber' shared service.
     *
     * @return \stdClass
     */
    protected function getSubscriberService()
    {
        $a = ($this->services['manager'] ?? $this->getManagerService());

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
        $a = ($this->services['foo6'] ?? $this->getFoo6Service());

        if (isset($this->privates['bar6'])) {
            return $this->privates['bar6'];
        }

        return $this->privates['bar6'] = new \stdClass($a);
    }

    /**
     * Gets the private 'level5' shared service.
     *
     * @return \stdClass
     */
    protected function getLevel5Service()
    {
        $a = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $this->privates['level5'] = $instance = new \stdClass($a);

        $a->call($instance);

        return $instance;
    }

    /**
     * Gets the private 'manager4' shared service.
     *
     * @return \stdClass
     */
    protected function getManager4Service($lazyLoad = true)
    {
        $a = ($this->services['connection4'] ?? $this->getConnection4Service());

        if (isset($this->privates['manager4'])) {
            return $this->privates['manager4'];
        }

        return $this->privates['manager4'] = new \stdClass($a);
    }
}
