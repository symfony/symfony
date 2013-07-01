<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class LazyServiceProjectServiceContainer extends Container
{
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
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @param boolean $lazyLoad whether to try lazy-loading the service with a proxy
     *
     * @return stdClass A stdClass instance.
     */
    public function getFooService($lazyLoad = true)
    {
        if ($lazyLoad) {
            $container = $this;

            return $this->services['foo'] = new stdClass_c1d194250ee2e2b7d2eab8b8212368a8(
                function (& $wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface $proxy) use ($container) {
                    $proxy->setProxyInitializer(null);

                    $wrappedInstance = $container->getFooService(false);

                    return true;
                }
            );
        }

        return new \stdClass();
    }
}

class stdClass_c1d194250ee2e2b7d2eab8b8212368a8 extends \stdClass implements \ProxyManager\Proxy\LazyLoadingInterface, \ProxyManager\Proxy\ValueHolderInterface
{

    /**
     * @var \Closure|null initializer responsible for generating the wrapped object
     */
    private $valueHolder5157dd96e88c0 = null;

    /**
     * @var \Closure|null initializer responsible for generating the wrapped object
     */
    private $initializer5157dd96e8924 = null;

    /**
     * @override constructor for lazy initialization
     *
     * @param \Closure|null $initializer
     */
    public function __construct($initializer)
    {
        $this->initializer5157dd96e8924 = $initializer;
    }

    /**
     * @param string $name
     */
    public function __get($name)
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__get', array('name' => $name));

        return $this->valueHolder5157dd96e88c0->$name;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__set', array('name' => $name, 'value' => $value));

        $this->valueHolder5157dd96e88c0->$name = $value;
    }

    /**
     * @param string $name
     */
    public function __isset($name)
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__isset', array('name' => $name));

        return isset($this->valueHolder5157dd96e88c0->$name);
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__unset', array('name' => $name));

        unset($this->valueHolder5157dd96e88c0->$name);
    }

    /**
     *
     */
    public function __clone()
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__clone', array());

        $this->valueHolder5157dd96e88c0 = clone $this->valueHolder5157dd96e88c0;
    }

    /**
     *
     */
    public function __sleep()
    {
        $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, '__sleep', array());

        return array('valueHolder5157dd96e88c0');
    }

    /**
     *
     */
    public function __wakeup()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setProxyInitializer(\Closure $initializer = null)
    {
        $this->initializer5157dd96e8924 = $initializer;
    }

    /**
     * {@inheritDoc}
     */
    public function getProxyInitializer()
    {
        return $this->initializer5157dd96e8924;
    }

    /**
     * {@inheritDoc}
     */
    public function initializeProxy()
    {
        return $this->initializer5157dd96e8924 && $this->initializer5157dd96e8924->__invoke($this->valueHolder5157dd96e88c0, $this, 'initializeProxy', array());
    }

    /**
     * {@inheritDoc}
     */
    public function isProxyInitialized()
    {
        return null !== $this->valueHolder5157dd96e88c0;
    }

    /**
     * {@inheritDoc}
     */
    public function getWrappedValueHolderValue()
    {
        return $this->valueHolder5157dd96e88c0;
    }

}
