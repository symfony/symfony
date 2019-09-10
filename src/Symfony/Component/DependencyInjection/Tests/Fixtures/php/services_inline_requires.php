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
class ProjectServiceContainer extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->parameters = $this->getDefaultParameters();

        $this->services = $this->privates = [];
        $this->methodMap = [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\ParentNotExists' => 'getParentNotExistsService',
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C1' => 'getC1Service',
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C2' => 'getC2Service',
        ];

        $this->aliases = [];

        $this->privates['service_container'] = function () {
            include_once \dirname(__DIR__, 1).'/includes/HotPath/I1.php';
            include_once \dirname(__DIR__, 1).'/includes/HotPath/P1.php';
            include_once \dirname(__DIR__, 1).'/includes/HotPath/T1.php';
            include_once \dirname(__DIR__, 1).'/includes/HotPath/C1.php';
        };
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
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C3' => true,
        ];
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists
     */
    protected function getParentNotExistsService()
    {
        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\ParentNotExists'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists();
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1
     */
    protected function getC1Service()
    {
        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C1'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C1();
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2
     */
    protected function getC2Service()
    {
        include_once \dirname(__DIR__, 1).'/includes/HotPath/C2.php';
        include_once \dirname(__DIR__, 1).'/includes/HotPath/C3.php';

        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\includes\\HotPath\\C2'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C2(new \Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath\C3());
    }

    public function getParameter(string $name)
    {
        if (!(isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }
        if (isset($this->loadedDynamicParameters[$name])) {
            return $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
        }

        return $this->parameters[$name];
    }

    public function hasParameter(string $name): bool
    {
        return isset($this->parameters[$name]) || isset($this->loadedDynamicParameters[$name]) || array_key_exists($name, $this->parameters);
    }

    public function setParameter(string $name, $value): void
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    public function getParameterBag(): ParameterBagInterface
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

    private $loadedDynamicParameters = [];
    private $dynamicParameters = [];

    private function getDynamicParameter(string $name)
    {
        throw new InvalidArgumentException(sprintf('The dynamic parameter "%s" must be defined.', $name));
    }

    protected function getDefaultParameters(): array
    {
        return [
            'inline_requires' => true,
        ];
    }
}
