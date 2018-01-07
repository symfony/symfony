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
class Symfony_DI_PhpDumper_Service_Locator_Argument extends Container
{
    private $parameters;
    private $targetDirs = [];
    private $getService;

    public function __construct()
    {
        $this->getService = \Closure::fromCallable([$this, 'getService']);
        $this->services = $this->privates = [];
        $this->syntheticIds = [
            'foo5' => true,
        ];
        $this->methodMap = [
            'bar' => 'getBarService',
            'foo1' => 'getFoo1Service',
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
            '.service_locator.38dy3OH' => true,
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'foo2' => true,
            'foo3' => true,
            'foo4' => true,
        ];
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \stdClass();

        $instance->locator = new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($this->getService, [
            'foo1' => ['services', 'foo1', 'getFoo1Service', false],
            'foo2' => ['privates', 'foo2', 'getFoo2Service', false],
            'foo3' => [false, 'foo3', 'getFoo3Service', false],
            'foo4' => ['privates', 'foo4', NULL, 'BOOM'],
            'foo5' => ['services', 'foo5', NULL, false],
        ], [
            'foo1' => '?',
            'foo2' => '?',
            'foo3' => '?',
            'foo4' => '?',
            'foo5' => '?',
        ]);

        return $instance;
    }

    /**
     * Gets the public 'foo1' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo1Service()
    {
        return $this->services['foo1'] = new \stdClass();
    }

    /**
     * Gets the private 'foo2' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo2Service()
    {
        return $this->privates['foo2'] = new \stdClass();
    }

    /**
     * Gets the private 'foo3' service.
     *
     * @return \stdClass
     */
    protected function getFoo3Service()
    {
        return new \stdClass();
    }

    /**
     * Gets the private 'foo4' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo4Service()
    {
        return $this->privates['foo4'] = new \stdClass();
    }
}
