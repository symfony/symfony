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
class Symfony_DI_PhpDumper_Test_EnvParameters extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->parameters = $this->getDefaultParameters();

        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar' => 'getBarService',
            'test' => 'getTestService',
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
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        ];
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Bar
     */
    protected function getBarService()
    {
        return $this->services['bar'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\Bar($this->getEnv('QUZ'));
    }

    /**
     * Gets the public 'test' shared service.
     *
     * @return object A %env(FOO)% instance
     */
    protected function getTestService()
    {
        return $this->services['test'] = new ${($_ = $this->getEnv('FOO')) && false ?: "_"}($this->getEnv('Bar'), 'foo'.$this->getEnv('string:FOO').'baz', $this->getEnv('int:Baz'));
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

    private $loadedDynamicParameters = [
        'bar' => false,
        'baz' => false,
        'json' => false,
        'db_dsn' => false,
    ];
    private $dynamicParameters = [];

    private function getDynamicParameter(string $name)
    {
        switch ($name) {
            case 'bar': $value = $this->getEnv('FOO'); break;
            case 'baz': $value = $this->getEnv('int:Baz'); break;
            case 'json': $value = $this->getEnv('json:file:json_file'); break;
            case 'db_dsn': $value = $this->getEnv('resolve:DB'); break;
            default: throw new InvalidArgumentException(sprintf('The dynamic parameter "%s" must be defined.', $name));
        }
        $this->loadedDynamicParameters[$name] = true;

        return $this->dynamicParameters[$name] = $value;
    }

    protected function getDefaultParameters(): array
    {
        return [
            'project_dir' => '/foo/bar',
            'env(FOO)' => 'foo',
            'env(DB)' => 'sqlite://%project_dir%/var/data.db',
            'env(json_file)' => (\dirname(__DIR__, 1).'/array.json'),
        ];
    }
}
