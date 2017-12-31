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
            'foo' => 'getFooService',
            'foo2' => 'getFoo2Service',
            'foo5' => 'getFoo5Service',
            'foobar4' => 'getFoobar4Service',
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
            'foo4' => true,
            'foobar' => true,
            'foobar2' => true,
            'foobar3' => true,
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

        $a = new \stdClass(${($_ = isset($this->services['foo5']) ? $this->services['foo5'] : $this->getFoo5Service()) && false ?: '_'});

        $a->foo = $instance;

        $instance->bar = $a;

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
}
