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
 *
 * @final since Symfony 3.3
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
        $this->normalizedIds = array(
            'symfony\\component\\dependencyinjection\\tests\\fixtures\\container33\\foo' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\Container33\\Foo',
        );
        $this->methodMap = array(
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\Container33\\Foo' => 'getSymfony_Component_DependencyInjection_Tests_Fixtures_Container33_FooService',
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the 'Symfony\Component\DependencyInjection\Tests\Fixtures\Container33\Foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container33\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Container33\Foo instance
     */
    protected function getSymfony_Component_DependencyInjection_Tests_Fixtures_Container33_FooService()
    {
        return $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\Container33\Foo'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\Container33\Foo();
    }
}
