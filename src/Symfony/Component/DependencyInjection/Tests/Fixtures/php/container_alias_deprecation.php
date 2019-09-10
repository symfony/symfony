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
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final
 */
class Symfony_DI_PhpDumper_Test_Aliases_Deprecation extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'foo' => 'getFooService',
            'alias_for_foo_deprecated' => 'getAliasForFooDeprecatedService',
        ];
        $this->aliases = [
            'alias_for_foo_non_deprecated' => 'foo',
        ];
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
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        ];
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \stdClass
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new \stdClass();
    }

    /**
     * Gets the public 'alias_for_foo_deprecated' alias.
     *
     * @return object The "foo" service.
     */
    protected function getAliasForFooDeprecatedService()
    {
        @trigger_error('The "alias_for_foo_deprecated" service alias is deprecated. You should stop using it, as it will be removed in the future.', E_USER_DEPRECATED);

        return $this->get('foo');
    }
}
