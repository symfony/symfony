<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    protected $parameters = [];
    protected \Closure $getService;

    public function __construct()
    {
        $this->getService = $this->getService(...);
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar' => 'getBarService',
            'baz' => 'getBazService',
        ];

        $this->aliases = [];
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function getRemovedIds(): array
    {
        return [
            '.service_locator.mtT6G8y' => true,
            'foo' => true,
        ];
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        return $this->services['bar'] = new \stdClass((new \stdClass()), (new \stdClass()));
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \stdClass
     */
    protected function getBazService()
    {
        return $this->services['baz'] = new \stdClass(new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($this->getService, [
            'foo' => [false, 'foo', 'getFooService', false],
        ], [
            'foo' => '?',
        ]));
    }

    /**
     * Gets the private 'foo' service.
     *
     * @return \stdClass
     */
    protected function getFooService()
    {
        $this->factories['service_container']['foo'] = function () {
            return new \stdClass();
        };

        return $this->factories['service_container']['foo']();
    }
}
