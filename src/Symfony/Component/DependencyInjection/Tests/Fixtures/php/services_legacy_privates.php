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
class Symfony_DI_PhpDumper_Test_Legacy_Privates extends Container
{
    private $parameters = [];
    private $targetDirs = [];

    public function __construct()
    {
        $dir = __DIR__;
        for ($i = 1; $i <= 5; ++$i) {
            $this->targetDirs[$i] = $dir = \dirname($dir);
        }
        $this->services = [];
        $this->methodMap = [
            'bar' => 'getBarService',
            'private' => 'getPrivateService',
            'private_alias' => 'getPrivateAliasService',
            'private_alias_decorator' => 'getPrivateAliasDecoratorService',
            'private_child' => 'getPrivateChildService',
            'private_decorator' => 'getPrivateDecoratorService',
            'private_not_inlined' => 'getPrivateNotInlinedService',
            'private_not_removed' => 'getPrivateNotRemovedService',
            'private_parent' => 'getPrivateParentService',
            'public_child' => 'getPublicChildService',
        ];
        $this->privates = [
            'decorated_private' => true,
            'decorated_private_alias' => true,
            'private' => true,
            'private_alias' => true,
            'private_child' => true,
            'private_not_inlined' => true,
            'private_not_removed' => true,
            'private_parent' => true,
        ];
        $this->aliases = [
            'alias_to_private' => 'private',
            'decorated_private' => 'private_decorator',
            'decorated_private_alias' => 'private_alias_decorator',
        ];
    }

    public function getRemovedIds()
    {
        return [
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'decorated_private' => true,
            'decorated_private_alias' => true,
            'foo' => true,
            'private' => true,
            'private_alias' => true,
            'private_alias_decorator.inner' => true,
            'private_child' => true,
            'private_decorator.inner' => true,
            'private_not_inlined' => true,
            'private_not_removed' => true,
            'private_parent' => true,
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

    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        return $this->services['bar'] = new \stdClass(${($_ = isset($this->services['private_not_inlined']) ? $this->services['private_not_inlined'] : ($this->services['private_not_inlined'] = new \stdClass())) && false ?: '_'});
    }

    /**
     * Gets the public 'private_alias_decorator' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateAliasDecoratorService()
    {
        return $this->services['private_alias_decorator'] = new \stdClass();
    }

    /**
     * Gets the public 'private_decorator' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateDecoratorService()
    {
        return $this->services['private_decorator'] = new \stdClass();
    }

    /**
     * Gets the public 'public_child' shared service.
     *
     * @return \stdClass
     */
    protected function getPublicChildService()
    {
        return $this->services['public_child'] = new \stdClass();
    }

    /**
     * Gets the private 'private' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateService()
    {
        return $this->services['private'] = new \stdClass();
    }

    /**
     * Gets the private 'private_alias' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateAliasService()
    {
        return $this->services['private_alias'] = new \stdClass();
    }

    /**
     * Gets the private 'private_child' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateChildService()
    {
        return $this->services['private_child'] = new \stdClass();
    }

    /**
     * Gets the private 'private_not_inlined' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateNotInlinedService()
    {
        return $this->services['private_not_inlined'] = new \stdClass();
    }

    /**
     * Gets the private 'private_not_removed' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateNotRemovedService()
    {
        return $this->services['private_not_removed'] = new \stdClass();
    }

    /**
     * Gets the private 'private_parent' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateParentService()
    {
        return $this->services['private_parent'] = new \stdClass();
    }
}
