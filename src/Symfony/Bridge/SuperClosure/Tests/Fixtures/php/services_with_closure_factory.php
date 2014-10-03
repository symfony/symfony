<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainerWithClosures
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainerWithClosures extends Container
{
    private static $parameters = array(
            'baz' => 42,
        );

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->services =
        $this->scopedServices =
        $this->scopeStacks = array();

        $this->set('service_container', $this);

        $this->scopes = array();
        $this->scopeChildren = array();
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
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance.
     */
    protected function getBarService()
    {
        return $this->services['bar'] = call_user_func(function (\stdClass $foo) {
    $bar = clone $foo;
    $bar->bar = 'bar';
    return $bar;
}, $this->get('foo'));
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance.
     */
    protected function getFooService()
    {
        return $this->services['foo'] = call_user_func(function ($baz) {
    $foo = new \stdClass();
    $foo->foo = $baz;
    return $foo;
}, 42);
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        $name = strtolower($name);

        if (!(isset(self::$parameters[$name]) || array_key_exists($name, self::$parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }

        return self::$parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name)
    {
        $name = strtolower($name);

        return isset(self::$parameters[$name]) || array_key_exists($name, self::$parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterBag()
    {
        if (null === $this->parameterBag) {
            $this->parameterBag = new FrozenParameterBag(self::$parameters);
        }

        return $this->parameterBag;
    }
}
