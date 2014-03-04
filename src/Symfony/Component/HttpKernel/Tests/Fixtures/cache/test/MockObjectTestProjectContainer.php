<?php
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
class MockObjectTestProjectContainer extends Container
{
    private $parameters;
    public function __construct()
    {
        $this->parameters = $this->getDefaultParameters();
        $this->services =
        $this->scopedServices =
        $this->scopeStacks = array();
        $this->set('service_container', $this);
        $this->scopes = array();
        $this->scopeChildren = array();
        $this->aliases = array();
    }
    public function getParameter($name)
    {
        $name = strtolower($name);
        if (!(isset($this->parameters[$name]) || array_key_exists($name, $this->parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }
        return $this->parameters[$name];
    }
    public function hasParameter($name)
    {
        $name = strtolower($name);
        return isset($this->parameters[$name]) || array_key_exists($name, $this->parameters);
    }
    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }
    public function getParameterBag()
    {
        if (null === $this->parameterBag) {
            $this->parameterBag = new FrozenParameterBag($this->parameters);
        }
        return $this->parameterBag;
    }
    protected function getDefaultParameters()
    {
        return array(
            'kernel.root_dir' => '/Users/fabien/Code/github/fabpot/symfony/src/Symfony/Component/HttpKernel/Tests/Fixtures',
            'kernel.environment' => 'test',
            'kernel.debug' => false,
            'kernel.name' => 'MockObject',
            'kernel.cache_dir' => '/Users/fabien/Code/github/fabpot/symfony/src/Symfony/Component/HttpKernel/Tests/Fixtures/cache/test',
            'kernel.logs_dir' => '/Users/fabien/Code/github/fabpot/symfony/src/Symfony/Component/HttpKernel/Tests/Fixtures/logs',
            'kernel.bundles' => array(
                'Mock_Bundle_f104cb4c' => 'Mock_Bundle_f104cb4c',
            ),
            'kernel.charset' => 'UTF-8',
            'kernel.container_class' => 'MockObjectTestProjectContainer',
        );
    }
}
