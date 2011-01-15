<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Resolves all parameter placeholders "%somevalue%" to their real values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveParameterPlaceHoldersPass implements CompilerPassInterface
{
    protected $parameterBag;

    public function process(ContainerBuilder $container)
    {
        $this->parameterBag = $container->getParameterBag();

        foreach ($container->getDefinitions() as $id => $definition) {
            $definition->setClass($this->resolveValue($definition->getClass()));
            $definition->setFile($this->resolveValue($definition->getFile()));
            $definition->setArguments($this->resolveValue($definition->getArguments()));

            $calls = array();
            foreach ($definition->getMethodCalls() as $name => $arguments) {
                $calls[$this->resolveValue($name)] = $this->resolveValue($arguments);
            }
            $definition->setMethodCalls($calls);
        }

        $aliases = array();
        foreach ($container->getAliases() as $name => $target) {
            $aliases[$this->resolveValue($name)] = $this->resolveValue($target);
        }
        $container->setAliases($aliases);

        $injectors = array();
        foreach ($container->getInterfaceInjectors() as $class => $injector) {
            $injector->setClass($this->resolveValue($injector->getClass()));
            $injectors[$this->resolveValue($class)] = $injector;
        }
        $container->setInterfaceInjectors($injectors);

        $parameterBag = $container->getParameterBag();
        foreach ($parameterBag->all() as $key => $value) {
            $parameterBag->set($key, $this->resolveValue($value));
        }
    }

    protected function resolveValue($value)
    {
        if (is_array($value)) {
            $resolved = array();
            foreach ($value as $k => $v) {
                $resolved[$this->resolveValue($k)] = $this->resolveValue($v);
            }

            return $resolved;
        } else if (is_string($value)) {
            return $this->resolveString($value);
        } else {
            return $value;
        }
    }

    public function resolveString($value)
    {
        if (preg_match('/^%[^%]+%$/', $value)) {
            return $this->resolveValue($this->parameterBag->resolveValue($value));
        }

        $self = $this;
        $parameterBag = $this->parameterBag;
        return preg_replace_callback('/(?<!%)%[^%]+%/',
            function($parameter) use ($self, $parameterBag) {
                $resolved = $parameterBag->resolveValue($parameter[0]);
                if (!is_string($resolved)) {
                    throw new \RuntimeException('You can only embed strings in other parameters.');
                }

                return $self->resolveString($resolved);
            },
            $value
        );
    }
}
