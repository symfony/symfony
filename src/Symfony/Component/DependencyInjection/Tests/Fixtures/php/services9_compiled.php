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
        $this->parameters = $this->getDefaultParameters();

        $this->services = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'configured_service' => 'getConfiguredServiceService',
            'configured_service_simple' => 'getConfiguredServiceSimpleService',
            'decorator_service' => 'getDecoratorServiceService',
            'decorator_service_with_name' => 'getDecoratorServiceWithNameService',
            'deprecated_service' => 'getDeprecatedServiceService',
            'factory_service' => 'getFactoryServiceService',
            'factory_service_simple' => 'getFactoryServiceSimpleService',
            'foo' => 'getFooService',
            'foo.baz' => 'getFoo_BazService',
            'foo_bar' => 'getFooBarService',
            'foo_with_inline' => 'getFooWithInlineService',
            'lazy_context' => 'getLazyContextService',
            'lazy_context_ignore_invalid_ref' => 'getLazyContextIgnoreInvalidRefService',
            'method_call1' => 'getMethodCall1Service',
            'new_factory_service' => 'getNewFactoryServiceService',
            'service_from_static_method' => 'getServiceFromStaticMethodService',
        );
        $this->aliases = array(
            'alias_for_alias' => 'foo',
            'alias_for_foo' => 'foo',
            'decorated' => 'decorator_service_with_name',
        );
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
     * Gets the public 'bar' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getBarService()
    {
        $a = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};

        $this->services['bar'] = $instance = new \Bar\FooClass('foo', $a, $this->getParameter('foo_bar'));

        $a->configure($instance);

        return $instance;
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \Baz
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \Baz();

        $instance->setFoo(${($_ = isset($this->services['foo_with_inline']) ? $this->services['foo_with_inline'] : $this->get('foo_with_inline')) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'configured_service' shared service.
     *
     * @return \stdClass
     */
    protected function getConfiguredServiceService()
    {
        $a = new \ConfClass();
        $a->setFoo(${($_ = isset($this->services['baz']) ? $this->services['baz'] : $this->get('baz')) && false ?: '_'});

        $this->services['configured_service'] = $instance = new \stdClass();

        $a->configureStdClass($instance);

        return $instance;
    }

    /**
     * Gets the public 'configured_service_simple' shared service.
     *
     * @return \stdClass
     */
    protected function getConfiguredServiceSimpleService()
    {
        $this->services['configured_service_simple'] = $instance = new \stdClass();

        (new \ConfClass('bar'))->configureStdClass($instance);

        return $instance;
    }

    /**
     * Gets the public 'decorator_service' shared service.
     *
     * @return \stdClass
     */
    protected function getDecoratorServiceService()
    {
        return $this->services['decorator_service'] = new \stdClass();
    }

    /**
     * Gets the public 'decorator_service_with_name' shared service.
     *
     * @return \stdClass
     */
    protected function getDecoratorServiceWithNameService()
    {
        return $this->services['decorator_service_with_name'] = new \stdClass();
    }

    /**
     * Gets the public 'deprecated_service' shared service.
     *
     * @return \stdClass
     *
     * @deprecated The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.
     */
    protected function getDeprecatedServiceService()
    {
        @trigger_error('The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.', E_USER_DEPRECATED);

        return $this->services['deprecated_service'] = new \stdClass();
    }

    /**
     * Gets the public 'factory_service' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceService()
    {
        return $this->services['factory_service'] = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'}->getInstance();
    }

    /**
     * Gets the public 'factory_service_simple' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceSimpleService()
    {
        return $this->services['factory_service_simple'] = (new \SimpleFactoryClass('foo'))->getInstance();
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getFooService()
    {
        $a = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};

        $this->services['foo'] = $instance = \Bar\FooClass::getInstance('foo', $a, array('bar' => 'foo is bar', 'foobar' => 'bar'), true, $this);

        $instance->foo = 'bar';
        $instance->moo = $a;
        $instance->qux = array('bar' => 'foo is bar', 'foobar' => 'bar');
        $instance->setBar(${($_ = isset($this->services['bar']) ? $this->services['bar'] : $this->get('bar')) && false ?: '_'});
        $instance->initialize();
        sc_configure($instance);

        return $instance;
    }

    /**
     * Gets the public 'foo.baz' shared service.
     *
     * @return \BazClass
     */
    protected function getFoo_BazService()
    {
        $this->services['foo.baz'] = $instance = \BazClass::getInstance();

        \BazClass::configureStatic1($instance);

        return $instance;
    }

    /**
     * Gets the public 'foo_bar' service.
     *
     * @return \Bar\FooClass
     */
    protected function getFooBarService()
    {
        return new \Bar\FooClass();
    }

    /**
     * Gets the public 'foo_with_inline' shared service.
     *
     * @return \Foo
     */
    protected function getFooWithInlineService()
    {
        $a = new \Bar();

        $this->services['foo_with_inline'] = $instance = new \Foo();

        $a->pub = 'pub';
        $a->setBaz(${($_ = isset($this->services['baz']) ? $this->services['baz'] : $this->get('baz')) && false ?: '_'});

        $instance->setBar($a);

        return $instance;
    }

    /**
     * Gets the public 'lazy_context' shared service.
     *
     * @return \LazyContext
     */
    protected function getLazyContextService()
    {
        return $this->services['lazy_context'] = new \LazyContext(new RewindableGenerator(function () {
            yield 'k1' => ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};
            yield 'k2' => $this;
        }, 2), new RewindableGenerator(function () {
            return new \EmptyIterator();
        }, 0));
    }

    /**
     * Gets the public 'lazy_context_ignore_invalid_ref' shared service.
     *
     * @return \LazyContext
     */
    protected function getLazyContextIgnoreInvalidRefService()
    {
        return $this->services['lazy_context_ignore_invalid_ref'] = new \LazyContext(new RewindableGenerator(function () {
            yield 0 => ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};
        }, 1), new RewindableGenerator(function () {
            return new \EmptyIterator();
        }, 0));
    }

    /**
     * Gets the public 'method_call1' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getMethodCall1Service()
    {
        require_once '%path%foo.php';

        $this->services['method_call1'] = $instance = new \Bar\FooClass();

        $instance->setBar(${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'});
        $instance->setBar(NULL);
        $instance->setBar((${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->foo() . (($this->hasParameter("foo")) ? ($this->getParameter("foo")) : ("default"))));

        return $instance;
    }

    /**
     * Gets the public 'new_factory_service' shared service.
     *
     * @return \FooBarBaz
     */
    protected function getNewFactoryServiceService()
    {
        $a = new \FactoryClass();
        $a->foo = 'bar';

        $this->services['new_factory_service'] = $instance = $a->getInstance();

        $instance->foo = 'bar';

        return $instance;
    }

    /**
     * Gets the public 'service_from_static_method' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getServiceFromStaticMethodService()
    {
        return $this->services['service_from_static_method'] = \Bar\FooClass::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        $name = strtolower($name);

        if (!(isset($this->parameters[$name]) || array_key_exists($name, $this->parameters) || isset($this->loadedDynamicParameters[$name]))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }
        if (isset($this->loadedDynamicParameters[$name])) {
            return $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name)
    {
        $name = strtolower($name);

        return isset($this->parameters[$name]) || array_key_exists($name, $this->parameters) || isset($this->loadedDynamicParameters[$name]);
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
            $parameters = $this->parameters;
            foreach ($this->loadedDynamicParameters as $name => $loaded) {
                $parameters[$name] = $loaded ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
            }
            $this->parameterBag = new FrozenParameterBag($parameters);
        }

        return $this->parameterBag;
    }

    private $loadedDynamicParameters = array();
    private $dynamicParameters = array();

    /**
     * Computes a dynamic parameter.
     *
     * @param string The name of the dynamic parameter to load
     *
     * @return mixed The value of the dynamic parameter
     *
     * @throws InvalidArgumentException When the dynamic parameter does not exist
     */
    private function getDynamicParameter($name)
    {
        throw new InvalidArgumentException(sprintf('The dynamic parameter "%s" must be defined.', $name));
    }

    /**
     * Gets the default parameters.
     *
     * @return array An array of the default parameters
     */
    protected function getDefaultParameters()
    {
        return array(
            'baz_class' => 'BazClass',
            'foo_class' => 'Bar\\FooClass',
            'foo' => 'bar',
        );
    }
}
