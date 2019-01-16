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
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'Bar\\Foo' => 'getFooService',
            'Foo\\Foo' => 'getFoo2Service',
        ];

        $this->aliases = [];
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
        return [
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        ];
    }

    /**
     * Gets the public 'Bar\Foo' shared service.
     *
     * @return \Bar\Foo
     */
    protected function getFooService()
    {
        return $this->services['Bar\Foo'] = new \Bar\Foo();
    }

    /**
     * Gets the public 'Foo\Foo' shared service.
     *
     * @return \Foo\Foo
     */
    protected function getFoo2Service()
    {
        return $this->services['Foo\Foo'] = new \Foo\Foo();
    }
}
