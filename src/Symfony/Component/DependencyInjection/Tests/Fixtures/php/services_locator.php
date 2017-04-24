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
        $this->methodMap = array(
            'bar_service' => 'getBarServiceService',
            'baz_service' => 'getBazServiceService',
            'foo_service' => 'getFooServiceService',
            'method_call_class' => 'getMethodCallClassService',
            'service_locator_with_inline_reference_1' => 'getServiceLocatorWithInlineReference1Service',
            'service_locator_with_inline_reference_2' => 'getServiceLocatorWithInlineReference2Service',
        );
        $this->privates = array(
            'baz_service' => true,
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
     * Gets the 'bar_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBarServiceService()
    {
        return $this->services['bar_service'] = new \stdClass(${($_ = isset($this->services['baz_service']) ? $this->services['baz_service'] : $this->getBazServiceService()) && false ?: '_'});
    }

    /**
     * Gets the 'foo_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\ServiceLocator A Symfony\Component\DependencyInjection\ServiceLocator instance
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\ServiceLocator(array('bar' => function () {
            return ${($_ = isset($this->services['bar_service']) ? $this->services['bar_service'] : $this->get('bar_service')) && false ?: '_'};
        }, 'baz' => function () {
            $f = function (\stdClass $v) { return $v; }; return $f(${($_ = isset($this->services['baz_service']) ? $this->services['baz_service'] : $this->getBazServiceService()) && false ?: '_'});
        }, 'nil' => function () {
            return NULL;
        }));
    }

    /**
     * Gets the 'method_call_class' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\MethodCallClass A Symfony\Component\DependencyInjection\Tests\Fixtures\MethodCallClass instance
     */
    protected function getMethodCallClassService()
    {
        return $this->services['method_call_class'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\MethodCallClass();
    }

    /**
     * Gets the 'service_locator_with_inline_reference_1' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\ServiceLocator A Symfony\Component\DependencyInjection\ServiceLocator instance
     */
    protected function getServiceLocatorWithInlineReference1Service()
    {
        $this->services['service_locator_with_inline_reference_1'] = $instance = new \Symfony\Component\DependencyInjection\ServiceLocator(array('method_call_class' => function () {
            return ${($_ = isset($this->services['method_call_class']) ? $this->services['method_call_class'] : $this->get('method_call_class')) && false ?: '_'};
        }));

        $instance->callableMethod('a', ${($_ = isset($this->services['method_call_class']) ? $this->services['method_call_class'] : $this->get('method_call_class')) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the 'service_locator_with_inline_reference_2' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\ServiceLocator A Symfony\Component\DependencyInjection\ServiceLocator instance
     */
    protected function getServiceLocatorWithInlineReference2Service()
    {
        $a = ${($_ = isset($this->services['method_call_class']) ? $this->services['method_call_class'] : $this->get('method_call_class')) && false ?: '_'};

        $this->services['service_locator_with_inline_reference_2'] = $instance = new \Symfony\Component\DependencyInjection\ServiceLocator(array('method_call_class' => function () use ($a) {
            return $a;
        }));

        $instance->callableMethod('a', $a);
        $instance->callableMethod('b', $a);

        return $instance;
    }

    /**
     * Gets the 'baz_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * This service is private.
     * If you want to be able to request this service from the container directly,
     * make it public, otherwise you might end up with broken code.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBazServiceService()
    {
        return $this->services['baz_service'] = new \stdClass();
    }
}
