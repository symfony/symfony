<?php

use Symphony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symphony\Component\DependencyInjection\Exception\LogicException;
use Symphony\Component\DependencyInjection\Exception\RuntimeException;
use Symphony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symphony Dependency Injection Component.
 *
 * @final since Symphony 3.3
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * @internal but protected for BC on cache:clear
     */
    protected $privates = array();

    public function __construct()
    {
        $dir = __DIR__;
        for ($i = 1; $i <= 5; ++$i) {
            $this->targetDirs[$i] = $dir = \dirname($dir);
        }
        $this->parameters = $this->getDefaultParameters();

        $this->services = $this->privates = array();
        $this->methodMap = array(
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\ParentNotExists' => 'getParentNotExistsService',
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C1' => 'getC1Service',
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C2' => 'getC2Service',
        );

        $this->aliases = array();

        $this->privates['service_container'] = function () {
            include_once $this->targetDirs[1].'/includes/HotPath/I1.php';
            include_once $this->targetDirs[1].'/includes/HotPath/P1.php';
            include_once $this->targetDirs[1].'/includes/HotPath/T1.php';
            include_once $this->targetDirs[1].'/includes/HotPath/C1.php';
        };
    }

    public function reset()
    {
        $this->privates = array();
        parent::reset();
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
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symphony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C3' => true,
        );
    }

    /**
     * Gets the public 'Symphony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists' shared service.
     *
     * @return \Symphony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists
     */
    protected function getParentNotExistsService()
    {
        return $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists();
    }

    /**
     * Gets the public 'Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1' shared service.
     *
     * @return \Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1
     */
    protected function getC1Service()
    {
        return $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1();
    }

    /**
     * Gets the public 'Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2' shared service.
     *
     * @return \Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2
     */
    protected function getC2Service()
    {
        include_once $this->targetDirs[1].'/includes/HotPath/C3.php';
        include_once $this->targetDirs[1].'/includes/HotPath/C2.php';

        return $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2(new \Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C3());
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

    private $loadedDynamicParameters = array();
    private $dynamicParameters = array();

    /**
     * Computes a dynamic parameter.
     *
     * @param string The name of the dynamic parameter to load
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
        return array(
            'inline_requires' => true,
        );
    }
}
