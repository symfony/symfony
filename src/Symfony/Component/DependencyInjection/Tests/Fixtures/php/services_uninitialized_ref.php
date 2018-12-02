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
class Symfony_DI_PhpDumper_Test_Uninitialized_Reference extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * @internal but protected for BC on cache:clear
     */
    protected $privates = array();

    public function __construct()
    {
        $this->services = $this->privates = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'foo1' => 'getFoo1Service',
        );

        $this->aliases = array();
    }

    public function reset()
    {
        $this->privates = array();
        parent::reset();
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function getRemovedIds()
    {
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'foo2' => true,
            'foo3' => true,
        );
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \stdClass();

        $instance->foo1 = ($this->services['foo1'] ?? null);
        $instance->foo2 = null;
        $instance->foo3 = ($this->privates['foo3'] ?? null);
        $instance->closures = array(0 => function () {
            return ($this->services['foo1'] ?? null);
        }, 1 => function () {
            return null;
        }, 2 => function () {
            return ($this->privates['foo3'] ?? null);
        });
        $instance->iter = new RewindableGenerator(function () {
            if (isset($this->services['foo1'])) {
                yield 'foo1' => ($this->services['foo1'] ?? null);
            }
            if (false) {
                yield 'foo2' => null;
            }
            if (isset($this->privates['foo3'])) {
                yield 'foo3' => ($this->privates['foo3'] ?? null);
            }
        }, function () {
            return 0 + (int) (isset($this->services['foo1'])) + (int) (false) + (int) (isset($this->privates['foo3']));
        });

        return $instance;
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \stdClass
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \stdClass();

        $instance->foo3 = ($this->privates['foo3'] ?? ($this->privates['foo3'] = new \stdClass()));

        return $instance;
    }

    /**
     * Gets the public 'foo1' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo1Service()
    {
        return $this->services['foo1'] = new \stdClass();
    }
}
