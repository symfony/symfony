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
        $this->parameters = $this->getDefaultParameters();

        $this->services = $this->privates = [];
        $this->syntheticIds = [
            'request' => true,
        ];
        $this->methodMap = [
            'BAR' => 'getBARService',
            'BAR2' => 'getBAR2Service',
            'bar' => 'getBar3Service',
            'bar2' => 'getBar22Service',
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
            'runtime_error' => 'getRuntimeErrorService',
            'service_from_static_method' => 'getServiceFromStaticMethodService',
            'tagged_iterator' => 'getTaggedIteratorService',
        ];
        $this->aliases = [
            'alias_for_alias' => 'foo',
            'alias_for_foo' => 'foo',
            'decorated' => 'decorator_service_with_name',
        ];
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
            'configurator_service' => true,
            'configurator_service_simple' => true,
            'decorated.pif-pouf' => true,
            'decorator_service.inner' => true,
            'errored_definition' => true,
            'factory_simple' => true,
            'inlined' => true,
            'new_factory' => true,
            'tagged_iterator_foo' => true,
        ];
    }

    /**
     * Gets the public 'BAR' shared service.
     *
     * @return \stdClass
     */
    protected function getBARService()
    {
        $this->services['BAR'] = $instance = new \stdClass();

        $instance->bar = ($this->services['bar'] ?? $this->getBar3Service());

        return $instance;
    }

    /**
     * Gets the public 'BAR2' shared service.
     *
     * @return \stdClass
     */
    protected function getBAR2Service()
    {
        return $this->services['BAR2'] = new \stdClass();
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getBar3Service()
    {
        $a = ($this->services['foo.baz'] ?? $this->getFoo_BazService());

        $this->services['bar'] = $instance = new \Bar\FooClass('foo', $a, $this->getParameter('foo_bar'));

        $a->configure($instance);

        return $instance;
    }

    /**
     * Gets the public 'bar2' shared service.
     *
     * @return \stdClass
     */
    protected function getBar22Service()
    {
        return $this->services['bar2'] = new \stdClass();
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \Baz
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \Baz();

        $instance->setFoo(($this->services['foo_with_inline'] ?? $this->getFooWithInlineService()));

        return $instance;
    }

    /**
     * Gets the public 'configured_service' shared service.
     *
     * @return \stdClass
     */
    protected function getConfiguredServiceService()
    {
        $this->services['configured_service'] = $instance = new \stdClass();

        $a = new \ConfClass();
        $a->setFoo(($this->services['baz'] ?? $this->getBazService()));

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
     * @deprecated The "deprecated_service" service is deprecated. You should stop using it, as it will be removed in the future.
     */
    protected function getDeprecatedServiceService()
    {
        @trigger_error('The "deprecated_service" service is deprecated. You should stop using it, as it will be removed in the future.', E_USER_DEPRECATED);

        return $this->services['deprecated_service'] = new \stdClass();
    }

    /**
     * Gets the public 'factory_service' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceService()
    {
        return $this->services['factory_service'] = ($this->services['foo.baz'] ?? $this->getFoo_BazService())->getInstance();
    }

    /**
     * Gets the public 'factory_service_simple' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceSimpleService()
    {
        return $this->services['factory_service_simple'] = $this->getFactorySimpleService()->getInstance();
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getFooService()
    {
        $a = ($this->services['foo.baz'] ?? $this->getFoo_BazService());

        $this->services['foo'] = $instance = \Bar\FooClass::getInstance('foo', $a, ['bar' => 'foo is bar', 'foobar' => 'bar'], true, $this);

        $instance->foo = 'bar';
        $instance->moo = $a;
        $instance->qux = ['bar' => 'foo is bar', 'foobar' => 'bar'];
        $instance->setBar(($this->services['bar'] ?? $this->getBar3Service()));
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
        return new \Bar\FooClass(($this->services['deprecated_service'] ?? $this->getDeprecatedServiceService()));
    }

    /**
     * Gets the public 'foo_with_inline' shared service.
     *
     * @return \Foo
     */
    protected function getFooWithInlineService()
    {
        $this->services['foo_with_inline'] = $instance = new \Foo();

        $a = new \Bar();
        $a->pub = 'pub';
        $a->setBaz(($this->services['baz'] ?? $this->getBazService()));

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
            yield 'k1' => ($this->services['foo.baz'] ?? $this->getFoo_BazService());
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
            yield 0 => ($this->services['foo.baz'] ?? $this->getFoo_BazService());
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
        include_once '%path%foo.php';

        $this->services['method_call1'] = $instance = new \Bar\FooClass();

        $instance->setBar(($this->services['foo'] ?? $this->getFooService()));
        $instance->setBar(NULL);
        $instance->setBar((($this->services['foo'] ?? $this->getFooService())->foo() . (($this->hasParameter("foo")) ? ($this->getParameter("foo")) : ("default"))));

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
     * Gets the public 'runtime_error' shared service.
     *
     * @return \stdClass
     */
    protected function getRuntimeErrorService()
    {
        return $this->services['runtime_error'] = new \stdClass($this->throw('Service "errored_definition" is broken.'));
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
     * Gets the public 'tagged_iterator' shared service.
     *
     * @return \Bar
     */
    protected function getTaggedIteratorService()
    {
        return $this->services['tagged_iterator'] = new \Bar(new RewindableGenerator(function () {
            yield 0 => ($this->services['foo'] ?? $this->getFooService());
            yield 1 => ($this->privates['tagged_iterator_foo'] ?? ($this->privates['tagged_iterator_foo'] = new \Bar()));
        }, 2));
    }

    /**
     * Gets the private 'factory_simple' shared service.
     *
     * @return \SimpleFactoryClass
     *
     * @deprecated The "factory_simple" service is deprecated. You should stop using it, as it will be removed in the future.
     */
    protected function getFactorySimpleService()
    {
        @trigger_error('The "factory_simple" service is deprecated. You should stop using it, as it will be removed in the future.', E_USER_DEPRECATED);

        return new \SimpleFactoryClass('foo');
    }

    public function getParameter($name)
    {
        $name = (string) $name;

        if (!(isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }
        if (isset($this->loadedDynamicParameters[$name])) {
            return $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
        }

        return $this->parameters[$name];
    }

    public function hasParameter($name)
    {
        $name = (string) $name;

        return isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters);
    }

    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

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

    private $loadedDynamicParameters = [];
    private $dynamicParameters = [];

    /**
     * Computes a dynamic parameter.
     *
     * @param string $name The name of the dynamic parameter to load
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
        return [
            'baz_class' => 'BazClass',
            'foo_class' => 'Bar\\FooClass',
            'foo' => 'bar',
        ];
    }

    protected function throw($message)
    {
        throw new RuntimeException($message);
    }
}
