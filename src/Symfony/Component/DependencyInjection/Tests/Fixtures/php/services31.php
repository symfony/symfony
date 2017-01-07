<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
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
            'bar' => 'getBarService',
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
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \stdClass();

        $instance->foo = array(0 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo::withNoArgs */ function () {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withNoArgs();
        }, 1 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo::withArgs */ function ($a, \Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo $b = NULL, $c = array(0 => 123)) {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withArgs($a, $b, $c);
        }, 2 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo::withRefs */ function &(&$a = NULL, &$b) {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withRefs($a, $b);
        });

        return $instance;
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo instance
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo();
    }
}
