<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class Symfony_DI_PhpDumper_Test_Callable_Adapter_Consumer extends Container
{
    protected $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar' => 'getBarService',
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
            'foo' => true,
        ];
    }

    /**
     * Gets the public 'bar' shared autowired service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Dumper\CallableAdapterConsumer
     */
    protected static function getBarService($container)
    {
        return $container->services['bar'] = new \Symfony\Component\DependencyInjection\Tests\Dumper\CallableAdapterConsumer(new class(function () { static $instance; return $instance ??= new \Symfony\Component\DependencyInjection\Tests\Compiler\Foo(); }) extends \Symfony\Component\DependencyInjection\Argument\LazyClosure implements \Symfony\Component\DependencyInjection\Tests\Compiler\SingleMethodInterface { public function theMethod() { return ($this->service)()->cloneFoo(...\func_get_args()); } });
    }
}
