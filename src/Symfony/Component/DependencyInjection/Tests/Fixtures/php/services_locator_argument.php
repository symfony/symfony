<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * Symfony_DI_PhpDumper_Test_Locator_Argument_Provide_Service_Locator.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Locator_Argument_Provide_Service_Locator extends Container
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
            'lazy_context' => 'getLazyContextService',
            'lazy_referenced' => 'getLazyReferencedService',
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
     * Gets the 'lazy_context' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \LazyContext A LazyContext instance
     */
    protected function getLazyContextService()
    {
        return $this->services['lazy_context'] = new \LazyContext(new ServiceLocator(array(
            'lazy1' => function () { return ${($_ = isset($this->services['lazy_referenced']) ? $this->services['lazy_referenced'] : $this->get('lazy_referenced')) && false ?: '_'}; },
            'lazy2' => function () { return ${($_ = isset($this->services['lazy_referenced']) ? $this->services['lazy_referenced'] : $this->get('lazy_referenced')) && false ?: '_'}; },
            'container' => function () { return $this; },
        )));
    }

    /**
     * Gets the 'lazy_referenced' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getLazyReferencedService()
    {
        return $this->services['lazy_referenced'] = new \stdClass();
    }
}
