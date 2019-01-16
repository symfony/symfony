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
            'bar_service' => 'getBarServiceService',
            'foo_service' => 'getFooServiceService',
            'translator.loader_1' => 'getTranslator_Loader1Service',
            'translator.loader_2' => 'getTranslator_Loader2Service',
            'translator.loader_3' => 'getTranslator_Loader3Service',
            'translator_1' => 'getTranslator1Service',
            'translator_2' => 'getTranslator2Service',
            'translator_3' => 'getTranslator3Service',
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
            'baz_service' => true,
            'translator.loader_1_locator' => true,
            'translator.loader_2_locator' => true,
            'translator.loader_3_locator' => true,
        ];
    }

    /**
     * Gets the public 'bar_service' shared service.
     *
     * @return \stdClass
     */
    protected function getBarServiceService()
    {
        return $this->services['bar_service'] = new \stdClass(($this->privates['baz_service'] ?? ($this->privates['baz_service'] = new \stdClass())));
    }

    /**
     * Gets the public 'foo_service' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\ServiceLocator
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\ServiceLocator(['bar' => function () {
            return ($this->services['bar_service'] ?? $this->getBarServiceService());
        }, 'baz' => function (): \stdClass {
            return ($this->privates['baz_service'] ?? ($this->privates['baz_service'] = new \stdClass()));
        }, 'nil' => function () {
            return NULL;
        }]);
    }

    /**
     * Gets the public 'translator.loader_1' shared service.
     *
     * @return \stdClass
     */
    protected function getTranslator_Loader1Service()
    {
        return $this->services['translator.loader_1'] = new \stdClass();
    }

    /**
     * Gets the public 'translator.loader_2' shared service.
     *
     * @return \stdClass
     */
    protected function getTranslator_Loader2Service()
    {
        return $this->services['translator.loader_2'] = new \stdClass();
    }

    /**
     * Gets the public 'translator.loader_3' shared service.
     *
     * @return \stdClass
     */
    protected function getTranslator_Loader3Service()
    {
        return $this->services['translator.loader_3'] = new \stdClass();
    }

    /**
     * Gets the public 'translator_1' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator
     */
    protected function getTranslator1Service()
    {
        return $this->services['translator_1'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator(new \Symfony\Component\DependencyInjection\ServiceLocator(['translator.loader_1' => function () {
            return ($this->services['translator.loader_1'] ?? ($this->services['translator.loader_1'] = new \stdClass()));
        }]));
    }

    /**
     * Gets the public 'translator_2' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator
     */
    protected function getTranslator2Service()
    {
        $this->services['translator_2'] = $instance = new \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator(new \Symfony\Component\DependencyInjection\ServiceLocator(['translator.loader_2' => function () {
            return ($this->services['translator.loader_2'] ?? ($this->services['translator.loader_2'] = new \stdClass()));
        }]));

        $instance->addResource('db', ($this->services['translator.loader_2'] ?? ($this->services['translator.loader_2'] = new \stdClass())), 'nl');

        return $instance;
    }

    /**
     * Gets the public 'translator_3' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator
     */
    protected function getTranslator3Service()
    {
        $this->services['translator_3'] = $instance = new \Symfony\Component\DependencyInjection\Tests\Fixtures\StubbedTranslator(new \Symfony\Component\DependencyInjection\ServiceLocator(['translator.loader_3' => function () {
            return ($this->services['translator.loader_3'] ?? ($this->services['translator.loader_3'] = new \stdClass()));
        }]));

        $a = ($this->services['translator.loader_3'] ?? ($this->services['translator.loader_3'] = new \stdClass()));

        $instance->addResource('db', $a, 'nl');
        $instance->addResource('db', $a, 'en');

        return $instance;
    }
}
