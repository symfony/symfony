<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Variable;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PhpDumper extends Dumper
{
    /**
     * Characters that might appear in the generated variable name as first character
     * @var string
     */
    const FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * Characters that might appear in the generated variable name as any but the first character
     * @var string
     */
    const NON_FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_';

    protected $inlinedDefinitions;
    protected $definitionVariables;
    protected $referenceVariables;
    protected $variableCount;
    protected $reservedVariables = array('instance', 'class');

    public function __construct(ContainerBuilder $container)
    {
        parent::__construct($container);

        $this->inlinedDefinitions = new \SplObjectStorage;
    }

    /**
     * Dumps the service container as a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param  array  $options An array of options
     *
     * @return string A PHP class representing of the service container
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => 'ProjectServiceContainer',
            'base_class' => 'Container',
        ), $options);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addServices().
            $this->addDefaultParametersMethod().
            $this->addInterfaceInjectors().
            $this->endClass()
        ;
    }

    protected function addInterfaceInjectors()
    {
        if ($this->container->isFrozen() || 0 === count($this->container->getInterfaceInjectors())) {
            return;
        }

        $code = <<<EOF

    /**
     * Applies all known interface injection calls
     *
     * @param Object \$instance
     */
    protected function applyInterfaceInjectors(\$instance)
    {

EOF;
        foreach ($this->container->getInterfaceInjectors() as $injector) {
            $code .= sprintf("        if (\$instance instanceof \\%s) {\n", $injector->getClass());
            foreach ($injector->getMethodCalls() as $call) {
                foreach ($call[1] as $value) {
                    $arguments[] = $this->dumpValue($value);
                }
                $code .= $this->wrapServiceConditionals($call[1], sprintf("            \$instance->%s(%s);\n", $call[0], implode(', ', $arguments)));
            }
            $code .= sprintf("        }\n");
        }
        $code .= <<<EOF
    }

EOF;
        return $code;
    }

    protected function addServiceLocalTempVariables($cId, $definition)
    {
        static $template = "        \$%s = %s;\n";

        $localDefinitions = array_merge(
            array($definition),
            $this->getInlinedDefinitions($definition)
        );

        $calls = $behavior = array();
        foreach ($localDefinitions as $iDefinition) {
            $this->getServiceCallsFromArguments($iDefinition->getArguments(), $calls, $behavior);
            $this->getServiceCallsFromArguments($iDefinition->getMethodCalls(), $calls, $behavior);
        }

        $code = '';
        foreach ($calls as $id => $callCount) {
            if ('service_container' === $id || $id === $cId) {
                continue;
            }

            if ($callCount > 1) {
                $name = $this->getNextVariableName();
                $this->referenceVariables[$id] = new Variable($name);

                if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $behavior[$id]) {
                    $code .= sprintf($template, $name, $this->getServiceCall($id));
                } else {
                    $code .= sprintf($template, $name, $this->getServiceCall($id, new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
                }
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    protected function addServiceInclude($id, $definition)
    {
        $template = "        require_once %s;\n";
        $code = '';

        if (null !== $file = $definition->getFile()) {
            $code .= sprintf($template, $this->dumpValue($file));
        }

        foreach ($this->getInlinedDefinitions($definition) as $definition) {
            if (null !== $file = $definition->getFile()) {
                $code .= sprintf($template, $this->dumpValue($file));
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    protected function addServiceInlinedDefinitions($id, $definition)
    {
        $code = '';
        $variableMap = $this->definitionVariables;
        $nbOccurrences = new \SplObjectStorage();
        $processed = new \SplObjectStorage();
        $inlinedDefinitions = $this->getInlinedDefinitions($definition);

        foreach ($inlinedDefinitions as $definition) {
            if (false === $nbOccurrences->contains($definition)) {
                $nbOccurrences->offsetSet($definition, 1);
            } else {
                $i = $nbOccurrences->offsetGet($definition);
                $nbOccurrences->offsetSet($definition, $i+1);
            }
        }

        foreach ($inlinedDefinitions as $sDefinition) {
            if ($processed->contains($sDefinition)) {
                continue;
            }
            $processed->offsetSet($sDefinition);

            $class = $this->dumpValue($sDefinition->getClass());
            if ($nbOccurrences->offsetGet($sDefinition) > 1 || count($sDefinition->getMethodCalls()) > 0 || null !== $sDefinition->getConfigurator() || false !== strpos($class, '$')) {
                $name = $this->getNextVariableName();
                $variableMap->offsetSet($sDefinition, new Variable($name));

                // a construct like:
                // $a = new ServiceA(ServiceB $b); $b = new ServiceB(ServiceA $a);
                // this is an indication for a wrong implementation, you can circumvent this problem
                // by setting up your service structure like this:
                // $b = new ServiceB();
                // $a = new ServiceA(ServiceB $b);
                // $b->setServiceA(ServiceA $a);
                if ($this->hasReference($id, $sDefinition->getArguments())) {
                    throw new \RuntimeException('Unresolvable reference detected in service definition for '.$id);
                }

                $arguments = array();
                foreach ($sDefinition->getArguments() as $argument) {
                    $arguments[] = $this->dumpValue($argument);
                }

                if (null !== $sDefinition->getFactoryMethod()) {
                    if (null !== $sDefinition->getFactoryService()) {
                        $code .= sprintf("        \$%s = %s->%s(%s);\n", $name, $this->getServiceCall($sDefinition->getFactoryService()), $sDefinition->getFactoryMethod(), implode(', ', $arguments));
                    } else {
                        $code .= sprintf("        \$%s = call_user_func(array(%s, '%s')%s);\n", $name, $class, $sDefinition->getFactoryMethod(), count($arguments) > 0 ? ', '.implode(', ', $arguments) : '');
                    }
                } elseif (false !== strpos($class, '$')) {
                    $code .= sprintf("        \$class = %s;\n        \$%s = new \$class(%s);\n", $class, $name, implode(', ', $arguments));
                } else {
                    $code .= sprintf("        \$%s = new \\%s(%s);\n", $name, substr(str_replace('\\\\', '\\', $class), 1, -1), implode(', ', $arguments));
                }

                if (!$this->hasReference($id, $sDefinition->getMethodCalls())) {
                    $code .= $this->addServiceMethodCalls(null, $sDefinition, $name);
                    $code .= $this->addServiceConfigurator(null, $sDefinition, $name);
                }

                $code .= "\n";
            }
        }

        return $code;
    }

    protected function addServiceReturn($id, $definition)
    {
        if ($this->isSimpleInstance($id, $definition)) {
            return "    }\n";
        }

        return "\n        return \$instance;\n    }\n";
    }

    protected function addServiceInstance($id, $definition)
    {
        $class = $this->dumpValue($definition->getClass());

        if (0 === strpos($class, "'") && !preg_match('/^\'[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid class name for the "%s" service.', $class, $id));
        }

        $arguments = array();
        foreach ($definition->getArguments() as $value) {
            $arguments[] = $this->dumpValue($value);
        }

        $simple = $this->isSimpleInstance($id, $definition);

        $instantiation = '';
        if (ContainerInterface::SCOPE_CONTAINER === $definition->getScope()) {
            $instantiation = "\$this->services['$id'] = ".($simple ? '' : '$instance');
        } else if (ContainerInterface::SCOPE_PROTOTYPE !== $scope = $definition->getScope()) {
            $instantiation = "\$this->services['$id'] = \$this->scopedServices['$scope']['$id'] = ".($simple ? '' : '$instance');
        } elseif (!$simple) {
            $instantiation = '$instance';
        }

        $return = '';
        if ($simple) {
            $return = 'return ';
        } else {
            $instantiation .= ' = ';
        }

        if (null !== $definition->getFactoryMethod()) {
            if (null !== $definition->getFactoryService()) {
                $code = sprintf("        $return{$instantiation}%s->%s(%s);\n", $this->getServiceCall($definition->getFactoryService()), $definition->getFactoryMethod(), implode(', ', $arguments));
            } else {
                $code = sprintf("        $return{$instantiation}call_user_func(array(%s, '%s')%s);\n", $class, $definition->getFactoryMethod(), $arguments ? ', '.implode(', ', $arguments) : '');
            }
        } elseif (false !== strpos($class, '$')) {
            $code = sprintf("        \$class = %s;\n        $return{$instantiation}new \$class(%s);\n", $class, implode(', ', $arguments));
        } else {
            $code = sprintf("        $return{$instantiation}new \\%s(%s);\n", substr(str_replace('\\\\', '\\', $class), 1, -1), implode(', ', $arguments));
        }

        if (!$simple) {
            $code .= "\n";
        }

        return $code;
    }

    protected function isSimpleInstance($id, $definition)
    {
        foreach (array_merge(array($definition), $this->getInlinedDefinitions($definition)) as $sDefinition) {
            if ($definition !== $sDefinition && !$this->hasReference($id, $sDefinition->getMethodCalls())) {
                continue;
            }

            if ($sDefinition->getMethodCalls() || $sDefinition->getConfigurator()) {
                return false;
            }
        }

        return true;
    }

    protected function addServiceMethodCalls($id, $definition, $variableName = 'instance')
    {
        $calls = '';
        foreach ($definition->getMethodCalls() as $call) {
            $arguments = array();
            foreach ($call[1] as $value) {
                $arguments[] = $this->dumpValue($value);
            }

            $calls .= $this->wrapServiceConditionals($call[1], sprintf("        \$%s->%s(%s);\n", $variableName, $call[0], implode(', ', $arguments)));
        }

        if (!$this->container->isFrozen() && count($this->container->getInterfaceInjectors()) > 0) {
            $calls = sprintf("\n        \$this->applyInterfaceInjectors(\$%s);\n", $variableName);
        }

        return $calls;
    }

    protected function addServiceInlinedDefinitionsSetup($id, $definition)
    {
        $this->referenceVariables[$id] = new Variable('instance');

        $code = '';
        $processed = new \SplObjectStorage();
        foreach ($this->getInlinedDefinitions($definition) as $iDefinition) {
            if ($processed->contains($iDefinition)) {
                continue;
            }
            $processed->offsetSet($iDefinition);

            if (!$this->hasReference($id, $iDefinition->getMethodCalls())) {
                continue;
            }

            if ($iDefinition->getMethodCalls()) {
                $code .= $this->addServiceMethodCalls(null, $iDefinition, (string) $this->definitionVariables->offsetGet($iDefinition));
            }
            if ($iDefinition->getConfigurator()) {
                $code .= $this->addServiceConfigurator(null, $iDefinition, (string) $this->definitionVariables->offsetGet($iDefinition));
            }
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    protected function addServiceConfigurator($id, $definition, $variableName = 'instance')
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (is_array($callable)) {
            if (is_object($callable[0]) && $callable[0] instanceof Reference) {
                return sprintf("        %s->%s(\$%s);\n", $this->getServiceCall((string) $callable[0]), $callable[1], $variableName);
            } else {
                return sprintf("        call_user_func(array(%s, '%s'), \$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
            }
        } else {
            return sprintf("        %s(\$%s);\n", $callable, $variableName);
        }
    }

    protected function addService($id, $definition)
    {
        $name = Container::camelize($id);
        $this->definitionVariables = new \SplObjectStorage();
        $this->referenceVariables = array();
        $this->variableCount = 0;

        $return = '';
        if ($definition->isSynthetic()) {
            $return = sprintf('@throws \RuntimeException always since this service is expected to be injected dynamically');
        } else if ($class = $definition->getClass()) {
            $return = sprintf("@return %s A %s instance.", 0 === strpos($class, '%') ? 'Object' : $class, $class);
        } elseif ($definition->getFactoryService()) {
            $return = sprintf('@return Object An instance returned by %s::%s().', $definition->getFactoryService(), $definition->getFactoryMethod());
        }

        $doc = '';
        if (ContainerInterface::SCOPE_PROTOTYPE !== $definition->getScope()) {
            $doc .= <<<EOF

     *
     * This service is shared.
     * This method always returns the same instance of the service.
EOF;
        }

        if (!$definition->isPublic()) {
            $doc .= <<<EOF

     *
     * This service is private.
     * If you want to be able to request this service from the container directly,
     * make it public, otherwise you might end up with broken code.
EOF;
        }

        $code = <<<EOF

    /**
     * Gets the '$id' service.$doc
     *
     * $return
     */
    protected function get{$name}Service()
    {

EOF;

        $scope = $definition->getScope();
        if (ContainerInterface::SCOPE_CONTAINER !== $scope && ContainerInterface::SCOPE_PROTOTYPE !== $scope) {
            $code .= <<<EOF
        if (!isset(\$this->scopedServices['$scope'])) {
            throw new \RuntimeException('You cannot create a service ("$id") of an inactive scope ("$scope").');
        }


EOF;
        }

        if ($definition->isSynthetic()) {
            $code .= sprintf("        throw new \RuntimeException('You have requested a synthetic service (\"%s\"). The DIC does not know how to construct this service.');\n    }\n", $id);
        } else {
            $code .=
                $this->addServiceInclude($id, $definition).
                $this->addServiceLocalTempVariables($id, $definition).
                $this->addServiceInlinedDefinitions($id, $definition).
                $this->addServiceInstance($id, $definition).
                $this->addServiceInlinedDefinitionsSetup($id, $definition).
                $this->addServiceMethodCalls($id, $definition).
                $this->addServiceConfigurator($id, $definition).
                $this->addServiceReturn($id, $definition)
            ;
        }

        $this->definitionVariables = null;
        $this->referenceVariables = null;

        return $code;
    }

    protected function addServiceAlias($alias, $id)
    {
        $name = Container::camelize($alias);
        $type = 'Object';

        if ($this->container->hasDefinition($id)) {
            $class = $this->container->getDefinition($id)->getClass();
            $type = 0 === strpos($class, '%') ? 'Object' : $class;
        }

        return <<<EOF

    /**
     * Gets the $alias service alias.
     *
     * @return $type An instance of the $id service
     */
    protected function get{$name}Service()
    {
        return {$this->getServiceCall($id)};
    }

EOF;
    }

    protected function addServices()
    {
        $publicServices = $privateServices = $aliasServices = '';
        foreach ($this->container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic()) {
                $publicServices .= $this->addService($id, $definition);
            } else {
                $privateServices .= $this->addService($id, $definition);
            }
        }

        foreach ($this->container->getAliases() as $alias => $id) {
            $aliasServices .= $this->addServiceAlias($alias, $id);
        }

        return $publicServices.$aliasServices.$privateServices;
    }

    protected function startClass($class, $baseClass)
    {
        $bagClass = $this->container->isFrozen() ? 'FrozenParameterBag' : 'ParameterBag';

        return <<<EOF
<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\\$bagClass;

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class $class extends $baseClass
{
EOF;
    }

    protected function addConstructor()
    {
        $bagClass = $this->container->isFrozen() ? 'FrozenParameterBag' : 'ParameterBag';

        $code = <<<EOF

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(new $bagClass(\$this->getDefaultParameters()));

EOF;

        if (count($scopes = $this->container->getScopes()) > 0) {
            $code .= "\n";
            $code .= "        \$this->scopes = ".$this->dumpValue($scopes).";\n";
            $code .= "        \$this->scopeChildren = ".$this->dumpValue($this->container->getScopeChildren()).";\n";
        }

        $code .= <<<EOF
    }

EOF;

        return $code;
    }

    protected function addDefaultParametersMethod()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        $parameters = $this->exportParameters($this->container->getParameterBag()->all());

        return <<<EOF

    /**
     * Gets the default parameters.
     *
     * @return array An array of the default parameters
     */
    protected function getDefaultParameters()
    {
        return $parameters;
    }

EOF;
    }

    protected function exportParameters($parameters, $indent = 12)
    {
        $php = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->exportParameters($value, $indent + 4);
            } elseif ($value instanceof Variable) {
                throw new \InvalidArgumentException(sprintf('you cannot dump a container with parameters that contain variable references. Variable "%s" found.', $variable));
            } elseif ($value instanceof Definition) {
                throw new \InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain service definitions. Definition for "%s" found.', $value->getClass()));
            } elseif ($value instanceof Reference) {
                throw new \InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain references to other services (reference to service %s found).', $value));
            } else {
                $value = var_export($value, true);
            }

            $php[] = sprintf('%s%s => %s,', str_repeat(' ', $indent), var_export($key, true), $value);
        }

        return sprintf("array(\n%s\n%s)", implode("\n", $php), str_repeat(' ', $indent - 4));
    }

    protected function endClass()
    {
        return <<<EOF
}

EOF;
    }

    protected function wrapServiceConditionals($value, $code)
    {
        if (!$services = ContainerBuilder::getServiceConditionals($value)) {
            return $code;
        }

        $conditions = array();
        foreach ($services as $service) {
            $conditions[] = sprintf("\$this->has('%s')", $service);
        }

        // re-indent the wrapped code
        $code = implode("\n", array_map(function ($line) { return $line ? '    '.$line : $line; }, explode("\n", $code)));

        return sprintf("        if (%s) {\n%s        }\n", implode(' && ', $conditions), $code);
    }

    protected function getServiceCallsFromArguments(array $arguments, array &$calls, array &$behavior)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->getServiceCallsFromArguments($argument, $calls, $behavior);
            } else if ($argument instanceof Reference) {
                $id = (string) $argument;

                if (!isset($calls[$id])) {
                    $calls[$id] = 0;
                }
                if (!isset($behavior[$id])) {
                    $behavior[$id] = $argument->getInvalidBehavior();
                } else if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $behavior[$id]) {
                    $behavior[$id] = $argument->getInvalidBehavior();
                }

                $calls[$id] += 1;
            }
        }
    }

    protected function getInlinedDefinitions(Definition $definition)
    {
        if (false === $this->inlinedDefinitions->contains($definition)) {
            $definitions = $this->getDefinitionsFromArguments($definition->getArguments());

            foreach ($definition->getMethodCalls() as $arguments) {
                $definitions = array_merge($definitions, $this->getDefinitionsFromArguments($arguments));
            }

            $this->inlinedDefinitions->offsetSet($definition, $definitions);

            return $definitions;
        }

        return $this->inlinedDefinitions->offsetGet($definition);
    }

    protected function getDefinitionsFromArguments(array $arguments)
    {
        $definitions = array();
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $definitions = array_merge($definitions, $this->getDefinitionsFromArguments($argument));
            } else if ($argument instanceof Definition) {
                $definitions = array_merge(
                    $definitions,
                    $this->getInlinedDefinitions($argument),
                    array($argument)
                );
            }
        }

        return $definitions;
    }

    protected function hasReference($id, array $arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                if ($this->hasReference($id, $argument)) {
                    return true;
                }
            } else if ($argument instanceof Reference) {
                if ($id === (string) $argument) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function dumpValue($value, $interpolate = true)
    {
        if (is_array($value)) {
            $code = array();
            foreach ($value as $k => $v) {
                $code[] = sprintf('%s => %s', $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate));
            }

            return sprintf('array(%s)', implode(', ', $code));
        } elseif (is_object($value) && $value instanceof Definition) {
            if (null !== $this->definitionVariables && $this->definitionVariables->contains($value)) {
                return $this->dumpValue($this->definitionVariables->offsetGet($value), $interpolate);
            }
            if (count($value->getMethodCalls()) > 0) {
                throw new \RuntimeException('Cannot dump definitions which have method calls.');
            }
            if (null !== $value->getConfigurator()) {
                throw new \RuntimeException('Cannot dump definitions which have a configurator.');
            }

            $arguments = array();
            foreach ($value->getArguments() as $argument) {
                $arguments[] = $this->dumpValue($argument);
            }
            $class = $this->dumpValue($value->getClass());

            if (false !== strpos($class, '$')) {
                throw new \RuntimeException('Cannot dump definitions which have a variable class name.');
            }

            if (null !== $value->getFactoryMethod()) {
                if (null !== $value->getFactoryService()) {
                    return sprintf("%s->%s(%s)", $this->getServiceCall($value->getFactoryService()), $value->getFactoryMethod(), implode(', ', $arguments));
                } else {
                    return sprintf("call_user_func(array(%s, '%s')%s)", $class, $value->getFactoryMethod(), count($arguments) > 0 ? ', '.implode(', ', $arguments) : '');
                }
            }

            return sprintf("new \\%s(%s)", substr(str_replace('\\\\', '\\', $class), 1, -1), implode(', ', $arguments));
        } elseif (is_object($value) && $value instanceof Variable) {
            return '$'.$value;
        } elseif (is_object($value) && $value instanceof Reference) {
            if (null !== $this->referenceVariables && isset($this->referenceVariables[$id = (string) $value])) {
                return $this->dumpValue($this->referenceVariables[$id], $interpolate);
            }

            return $this->getServiceCall((string) $value, $value);
        } elseif (is_object($value) && $value instanceof Parameter) {
            return $this->dumpParameter($value);
        } elseif (true === $interpolate && is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (Boolean, integer, ...)
                // the preg_replace_callback converts them to strings
                return $this->dumpParameter(strtolower($match[1]));
            } else {
                $that = $this;
                $replaceParameters = function ($match) use ($that)
                {
                    return sprintf("'.".$that->dumpParameter(strtolower($match[2])).".'");
                };

                $code = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameters, var_export($value, true)));

                // optimize string
                $code = preg_replace(array("/^''\./", "/\.''$/", "/'\.'/", "/\.''\./"), array('', '', '', '.'), $code);

                return $code;
            }
        } elseif (is_object($value) || is_resource($value)) {
            throw new \RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        } else {
            return var_export($value, true);
        }
    }

    public function dumpParameter($name)
    {
        if ($this->container->isFrozen() && $this->container->hasParameter($name)) {
            return $this->dumpValue($this->container->getParameter($name), false);
        }

        return sprintf("\$this->getParameter('%s')", strtolower($name));
    }

    protected function getServiceCall($id, Reference $reference = null)
    {
        if ('service_container' === $id) {
            return '$this';
        }

        if (null !== $reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            return sprintf('$this->get(\'%s\', ContainerInterface::NULL_ON_INVALID_REFERENCE)', $id);
        } else {
            if ($this->container->hasAlias($id)) {
                $id = (string) $this->container->getAlias($id);
            }

            return sprintf('$this->get(\'%s\')', $id);
        }
    }

    /**
     * Returns the next name to use
     *
     * @return string
     */
    protected function getNextVariableName()
    {
        $firstChars = self::FIRST_CHARS;
        $firstCharsLength = strlen($firstChars);
        $nonFirstChars = self::NON_FIRST_CHARS;
        $nonFirstCharsLength = strlen($nonFirstChars);

        while (true)
        {
            $name = '';
            $i = $this->variableCount;

            if ('' === $name)
            {
                $name .= $firstChars[$i%$firstCharsLength];
                $i = intval($i/$firstCharsLength);
            }

            while ($i > 0)
            {
                $i -= 1;
                $name .= $nonFirstChars[$i%$nonFirstCharsLength];
                $i = intval($i/$nonFirstCharsLength);
            }

            $this->variableCount += 1;

            // check that the name is not reserved
            if (in_array($name, $this->reservedVariables, true)) {
                continue;
            }

            return $name;
        }
    }
}
