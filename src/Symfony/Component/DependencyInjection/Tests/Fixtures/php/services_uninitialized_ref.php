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
class Symfony_DI_PhpDumper_Test_Uninitialized_Reference extends Container
{
    private $parameters;
    private $targetDirs = array();

    public function __construct()
    {
        $this->services = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'foo1' => 'getFoo1Service',
            'foo3' => 'getFoo3Service',
        );
        $this->privates = array(
            'foo3' => true,
        );

        $this->aliases = array();
    }

    public function getRemovedIds()
    {
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'foo2' => true,
            'foo3' => true,
        );
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
        $this->services['bar'] = $instance = new \stdClass();

        $instance->foo1 = ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'};
        $instance->foo2 = null;
        $instance->foo3 = ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'};
        $instance->closures = array(0 => function () {
            return ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'};
        }, 1 => function () {
            return null;
        }, 2 => function () {
            return ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'};
        });
        $instance->iter = new RewindableGenerator(function () {
            if (isset($this->services['foo1'])) {
                yield 'foo1' => ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'};
            }
            if (false) {
                yield 'foo2' => null;
            }
            if (isset($this->services['foo3'])) {
                yield 'foo3' => ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'};
            }
        }, function () {
            return 0 + (int) (isset($this->services['foo1'])) + (int) (false) + (int) (isset($this->services['foo3']));
        });

        return $instance;
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \stdClass
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \stdClass();

        $instance->foo3 = ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : $this->services['foo3'] = new \stdClass()) && false ?: '_'};

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
     * Gets the private 'foo3' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo3Service()
    {
        return $this->services['foo3'] = new \stdClass();
    }
}
