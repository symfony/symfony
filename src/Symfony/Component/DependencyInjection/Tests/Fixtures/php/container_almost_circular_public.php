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
    private $privates = array();

    public function __construct()
    {
        $this->services = $this->privates = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'foo' => 'getFooService',
            'foobar' => 'getFoobarService',
        );

        $this->aliases = array();
    }

    public function reset(): void
    {
        $this->privates = array();
        parent::reset();
    }

    public function compile(): void
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
        );
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
}
