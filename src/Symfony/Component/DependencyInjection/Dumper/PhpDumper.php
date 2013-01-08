<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;

/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @api
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

    private $inlinedDefinitions;
    private $definitionVariables;
    private $referenceVariables;
    private $variableCount;
    private $reservedVariables = array('instance', 'class');

    /**
     * {@inheritDoc}
     *
     * @api
     */
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
     * @param array $options An array of options
     *
     * @return string A PHP class representing of the service container
     *
     * @api
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => 'ProjectServiceContainer',
            'base_class' => 'Container',
        ), $options);

        $code = $this->startClass($options['class'], $options['base_class']);

        if ($this->container->isFrozen()) {
            $code .= $this->addFrozenConstructor();
        } else {
            $code .= $this->addConstructor();
        }

        $code .=
            $this->addServices().
            $this->addDefaultParametersMethod().
            $this->endClass()
        ;

        return $code;
    }

    /**
     * Generates Service local temp variables.
     *
     * @param string $cId
     * @param string $definition
     *
     * @return string
     */
    private function addServiceLocalTempVariables($cId, $definition)
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
            $this->getServiceCallsFromArguments($iDefinition->getProperties(), $calls, $behavior);
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

    /**
     * Generates the require_once statement for service includes.
     *
     * @param string     $id         The service id
     * @param Definition $definition
     *
     * @return string
     */
    private function addServiceInclude($id, $definition)
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

    /**
     * Generates the inline definition of a service.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return string
     *
     * @throws \RuntimeException When the factory definition is incomplete
     * @throws ServiceCircularReferenceException When a circular reference is detected
     */
    private function addServiceInlinedDefinitions($id, $definition)
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
                $nbOccurrences->offsetSet($definition, $i + 1);
            }
        }

        foreach ($inlinedDefinitions as $sDefinition) {
            if ($processed->contains($sDefinition)) {
                continue;
            }
            $processed->offsetSet($sDefinition);

            $class = $this->dumpValue($sDefinition->getClass());
            if ($nbOccurrences->offsetGet($sDefinition) > 1 || $sDefinition->getMethodCalls() || $sDefinition->getProperties() || null !== $sDefinition->getConfigurator() || false !== strpos($class, '$')) {
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
                    throw new ServiceCircularReferenceException($id, array($id));
                }

                $code .= $this->addNewInstance($id, $sDefinition, '$'.$name, ' = ');

                if (!$this->hasReference($id, $sDefinition->getMethodCalls(), true) && !$this->hasReference($id, $sDefinition->getProperties(), true)) {
                    $code .= $this->addServiceMethodCalls(null, $sDefinition, $name);
                    $code .= $this->addServiceProperties(null, $sDefinition, $name);
                    $code .= $this->addServiceConfigurator(null, $sDefinition, $name);
                }

                $code .= "\n";
            }
        }

        return $code;
    }

    /**
     * Adds the service return statement.
     *
     * @param string     $id         Service id
     * @param Definition $definition
     *
     * @return string
     */
    private function addServiceReturn($id, $definition)
    {
        if ($this->isSimpleInstance($id, $definition)) {
            return "    }\n";
        }

        return "\n        return \$instance;\n    }\n";
    }

    /**
     * Generates the service instance.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return string
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function addServiceInstance($id, $definition)
    {
        $class = $this->dumpValue($definition->getClass());

        if (0 === strpos($class, "'") && !preg_match('/^\'[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid class name for the "%s" service.', $class, $id));
        }

        $simple = $this->isSimpleInstance($id, $definition);

        $instantiation = '';
        if (ContainerInterface::SCOPE_CONTAINER === $definition->getScope()) {
            $instantiation = "\$this->services['$id'] = ".($simple ? '' : '$instance');
        } elseif (ContainerInterface::SCOPE_PROTOTYPE !== $scope = $definition->getScope()) {
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

        $code = $this->addNewInstance($id, $definition, $return, $instantiation);

        if (!$simple) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Checks if the definition is a simple instance.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return Boolean
     */
    private function isSimpleInstance($id, $definition)
    {
        foreach (array_merge(array($definition), $this->getInlinedDefinitions($definition)) as $sDefinition) {
            if ($definition !== $sDefinition && !$this->hasReference($id, $sDefinition->getMethodCalls())) {
                continue;
            }

            if ($sDefinition->getMethodCalls() || $sDefinition->getProperties() || $sDefinition->getConfigurator()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds method calls to a service definition.
     *
     * @param string     $id
     * @param Definition $definition
     * @param string     $variableName
     *
     * @return string
     */
    private function addServiceMethodCalls($id, $definition, $variableName = 'instance')
    {
        $calls = '';
        foreach ($definition->getMethodCalls() as $call) {
            $arguments = array();
            foreach ($call[1] as $value) {
                $arguments[] = $this->dumpValue($value);
            }

            $calls .= $this->wrapServiceConditionals($call[1], sprintf("        \$%s->%s(%s);\n", $variableName, $call[0], implode(', ', $arguments)));
        }

        return $calls;
    }

    private function addServiceProperties($id, $definition, $variableName = 'instance')
    {
        $code = '';
        foreach ($definition->getProperties() as $name => $value) {
            $code .= sprintf("        \$%s->%s = %s;\n", $variableName, $name, $this->dumpValue($value));
        }

        return $code;
    }

    /**
     * Generates the inline definition setup.
     *
     * @param string     $id
     * @param Definition $definition
     * @return string
     */
    private function addServiceInlinedDefinitionsSetup($id, $definition)
    {
        $this->referenceVariables[$id] = new Variable('instance');

        $code = '';
        $processed = new \SplObjectStorage();
        foreach ($this->getInlinedDefinitions($definition) as $iDefinition) {
            if ($processed->contains($iDefinition)) {
                continue;
            }
            $processed->offsetSet($iDefinition);

            if (!$this->hasReference($id, $iDefinition->getMethodCalls(), true) && !$this->hasReference($id, $iDefinition->getProperties(), true)) {
                continue;
            }

            $name = (string) $this->definitionVariables->offsetGet($iDefinition);
            $code .= $this->addServiceMethodCalls(null, $iDefinition, $name);
            $code .= $this->addServiceProperties(null, $iDefinition, $name);
            $code .= $this->addServiceConfigurator(null, $iDefinition, $name);
        }

        if ('' !== $code) {
            $code .= "\n";
        }

        return $code;
    }

    /**
     * Adds configurator definition
     *
     * @param string     $id
     * @param Definition $definition
     * @param string     $variableName
     *
     * @return string
     */
    private function addServiceConfigurator($id, $definition, $variableName = 'instance')
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (is_array($callable)) {
            if (is_object($callable[0]) && $callable[0] instanceof Reference) {
                return sprintf("        %s->%s(\$%s);\n", $this->getServiceCall((string) $callable[0]), $callable[1], $variableName);
            }

            return sprintf("        call_user_func(array(%s, '%s'), \$%s);\n", $this->dumpValue($callable[0]), $callable[1], $variableName);
        }

        return sprintf("        %s(\$%s);\n", $callable, $variableName);
    }

    /**
     * Adds a service
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return string
     */
    private function addService($id, $definition)
    {
        $name = Container::camelize($id);
        $this->definitionVariables = new \SplObjectStorage();
        $this->referenceVariables = array();
        $this->variableCount = 0;

        $return = '';
        if ($definition->isSynthetic()) {
            $return = sprintf('@throws RuntimeException always since this service is expected to be injected dynamically');
        } elseif ($class = $definition->getClass()) {
            $return = sprintf("@return %s A %s instance.", 0 === strpos($class, '%') ? 'Object' : $class, $class);
        } elseif ($definition->getFactoryClass()) {
            $return = sprintf('@return Object An instance returned by %s::%s().', $definition->getFactoryClass(), $definition->getFactoryMethod());
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
            throw new InactiveScopeException('$id', '$scope');
        }


EOF;
        }

        if ($definition->isSynthetic()) {
            $code .= sprintf("        throw new RuntimeException('You have requested a synthetic service (\"%s\"). The DIC does not know how to construct this service.');\n    }\n", $id);
        } else {
            $code .=
                $this->addServiceInclude($id, $definition).
                $this->addServiceLocalTempVariables($id, $definition).
                $this->addServiceInlinedDefinitions($id, $definition).
                $this->addServiceInstance($id, $definition).
                $this->addServiceInlinedDefinitionsSetup($id, $definition).
                $this->addServiceMethodCalls($id, $definition).
                $this->addServiceProperties($id, $definition).
                $this->addServiceConfigurator($id, $definition).
                $this->addServiceReturn($id, $definition)
            ;
        }

        $this->definitionVariables = null;
        $this->referenceVariables = null;

        return $code;
    }

    /**
     * Adds a service alias.
     *
     * @param string $alias
     * @param string $id
     *
     * @return string
     */
    private function addServiceAlias($alias, $id)
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

    /**
     * Adds multiple services
     *
     * @return string
     */
    private function addServices()
    {
        $publicServices = $privateServices = $aliasServices = '';
        $definitions = $this->container->getDefinitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if ($definition->isPublic()) {
                $publicServices .= $this->addService($id, $definition);
            } else {
                $privateServices .= $this->addService($id, $definition);
            }
        }

        $aliases = $this->container->getAliases();
        ksort($aliases);
        foreach ($aliases as $alias => $id) {
            $aliasServices .= $this->addServiceAlias($alias, $id);
        }

        return $publicServices.$aliasServices.$privateServices;
    }

    private function addNewInstance($id, Definition $definition, $return, $instantiation)
    {
        $class = $this->dumpValue($definition->getClass());

        $arguments = array();
        foreach ($definition->getArguments() as $value) {
            $arguments[] = $this->dumpValue($value);
        }

        if (null !== $definition->getFactoryMethod()) {
            if (null !== $definition->getFactoryClass()) {
                return sprintf("        $return{$instantiation}call_user_func(array(%s, '%s')%s);\n", $this->dumpValue($definition->getFactoryClass()), $definition->getFactoryMethod(), $arguments ? ', '.implode(', ', $arguments) : '');
            }

            if (null !== $definition->getFactoryService()) {
                return sprintf("        $return{$instantiation}%s->%s(%s);\n", $this->getServiceCall($definition->getFactoryService()), $definition->getFactoryMethod(), implode(', ', $arguments));
            }

            throw new RuntimeException('Factory method requires a factory service or factory class in service definition for '.$id);
        }

        if (false !== strpos($class, '$')) {
            return sprintf("        \$class = %s;\n\n        $return{$instantiation}new \$class(%s);\n", $class, implode(', ', $arguments));
        }

        return sprintf("        $return{$instantiation}new \\%s(%s);\n", substr(str_replace('\\\\', '\\', $class), 1, -1), implode(', ', $arguments));
    }

    /**
     * Adds the class headers.
     *
     * @param string $class     Class name
     * @param string $baseClass The name of the base class
     *
     * @return string
     */
    private function startClass($class, $baseClass)
    {
        $bagClass = $this->container->isFrozen() ? 'use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;' : 'use Symfony\Component\DependencyInjection\ParameterBag\\ParameterBag;';

        return <<<EOF
<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
$bagClass

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

    /**
     * Adds the constructor.
     *
     * @return string
     */
    private function addConstructor()
    {
        $arguments = $this->container->getParameterBag()->all() ? 'new ParameterBag($this->getDefaultParameters())' : null;

        $code = <<<EOF

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct($arguments);

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

    /**
     * Adds the constructor for a frozen container.
     *
     * @return string
     */
    private function addFrozenConstructor()
    {
        $code = <<<EOF

    /**
     * Constructor.
     */
    public function __construct()
    {
EOF;

        if ($this->container->getParameterBag()->all()) {
            $code .= "\n        \$this->parameters = \$this->getDefaultParameters();\n";
        }

        $code .= <<<EOF

        \$this->services =
        \$this->scopedServices =
        \$this->scopeStacks = array();

        \$this->set('service_container', \$this);

EOF;

        $code .= "\n";
        if (count($scopes = $this->container->getScopes()) > 0) {
            $code .= "        \$this->scopes = ".$this->dumpValue($scopes).";\n";
            $code .= "        \$this->scopeChildren = ".$this->dumpValue($this->container->getScopeChildren()).";\n";
        } else {
            $code .= "        \$this->scopes = array();\n";
            $code .= "        \$this->scopeChildren = array();\n";
        }

        $code .= <<<EOF
    }

EOF;

        return $code;
    }

    /**
     * Adds default parameters method.
     *
     * @return string
     */
    private function addDefaultParametersMethod()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        $parameters = $this->exportParameters($this->container->getParameterBag()->all());

        $code = '';
        if ($this->container->isFrozen()) {
            $code .= <<<EOF

    /**
     * {@inheritdoc}
     */
    public function getParameter(\$name)
    {
        \$name = strtolower(\$name);

        if (!array_key_exists(\$name, \$this->parameters)) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', \$name));
        }

        return \$this->parameters[\$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter(\$name)
    {
        return array_key_exists(strtolower(\$name), \$this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter(\$name, \$value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritDoc}
     */
    public function getParameterBag()
    {
        if (null === \$this->parameterBag) {
            \$this->parameterBag = new FrozenParameterBag(\$this->parameters);
        }

        return \$this->parameterBag;
    }
EOF;
        }

        $code .= <<<EOF

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

        return $code;
    }

    /**
     * Exports parameters.
     *
     * @param array   $parameters
     * @param string  $path
     * @param integer $indent
     *
     * @return string
     */
    private function exportParameters($parameters, $path = '', $indent = 12)
    {
        $php = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->exportParameters($value, $path.'/'.$key, $indent + 4);
            } elseif ($value instanceof Variable) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain variable references. Variable "%s" found in "%s".', $value, $path.'/'.$key));
            } elseif ($value instanceof Definition) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain service definitions. Definition for "%s" found in "%s".', $value->getClass(), $path.'/'.$key));
            } elseif ($value instanceof Reference) {
                throw new InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain references to other services (reference to service "%s" found in "%s").', $value, $path.'/'.$key));
            } else {
                $value = var_export($value, true);
            }

            $php[] = sprintf('%s%s => %s,', str_repeat(' ', $indent), var_export($key, true), $value);
        }

        return sprintf("array(\n%s\n%s)", implode("\n", $php), str_repeat(' ', $indent - 4));
    }

    /**
     * Ends the class definition.
     *
     * @return string
     */
    private function endClass()
    {
        return <<<EOF
}

EOF;
    }

    /**
     * Wraps the service conditionals.
     *
     * @param string $value
     * @param string $code
     *
     * @return string
     */
    private function wrapServiceConditionals($value, $code)
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

    /**
     * Builds service calls from arguments
     *
     * @param array  $arguments
     * @param array  &$calls    By reference
     * @param array  &$behavior By reference
     */
    private function getServiceCallsFromArguments(array $arguments, array &$calls, array &$behavior)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->getServiceCallsFromArguments($argument, $calls, $behavior);
            } elseif ($argument instanceof Reference) {
                $id = (string) $argument;

                if (!isset($calls[$id])) {
                    $calls[$id] = 0;
                }
                if (!isset($behavior[$id])) {
                    $behavior[$id] = $argument->getInvalidBehavior();
                } elseif (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $behavior[$id]) {
                    $behavior[$id] = $argument->getInvalidBehavior();
                }

                $calls[$id] += 1;
            }
        }
    }

    /**
     * Returns the inline definition
     *
     * @param Definition $definition
     *
     * @return array
     */
    private function getInlinedDefinitions(Definition $definition)
    {
        if (false === $this->inlinedDefinitions->contains($definition)) {
            $definitions = array_merge(
                $this->getDefinitionsFromArguments($definition->getArguments()),
                $this->getDefinitionsFromArguments($definition->getMethodCalls()),
                $this->getDefinitionsFromArguments($definition->getProperties())
            );

            $this->inlinedDefinitions->offsetSet($definition, $definitions);

            return $definitions;
        }

        return $this->inlinedDefinitions->offsetGet($definition);
    }

    /**
     * Gets the definition from arguments
     *
     * @param array $arguments
     *
     * @return array
     */
    private function getDefinitionsFromArguments(array $arguments)
    {
        $definitions = array();
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $definitions = array_merge($definitions, $this->getDefinitionsFromArguments($argument));
            } elseif ($argument instanceof Definition) {
                $definitions = array_merge(
                    $definitions,
                    $this->getInlinedDefinitions($argument),
                    array($argument)
                );
            }
        }

        return $definitions;
    }

    /**
     * Checks if a service id has a reference
     *
     * @param string $id
     * @param array  $arguments
     *
     * @return Boolean
     */
    private function hasReference($id, array $arguments, $deep = false)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                if ($this->hasReference($id, $argument, $deep)) {
                    return true;
                }
            } elseif ($argument instanceof Reference) {
                if ($id === (string) $argument) {
                    return true;
                }

                if ($deep) {
                    $service = $this->container->getDefinition((string) $argument);
                    $arguments = array_merge($service->getMethodCalls(), $service->getArguments(), $service->getProperties());

                    if ($this->hasReference($id, $arguments, $deep)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Dumps values.
     *
     * @param array   $value
     * @param Boolean $interpolate
     *
     * @return string
     */
    private function dumpValue($value, $interpolate = true)
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
                throw new RuntimeException('Cannot dump definitions which have method calls.');
            }
            if (null !== $value->getConfigurator()) {
                throw new RuntimeException('Cannot dump definitions which have a configurator.');
            }

            $arguments = array();
            foreach ($value->getArguments() as $argument) {
                $arguments[] = $this->dumpValue($argument);
            }
            $class = $this->dumpValue($value->getClass());

            if (false !== strpos($class, '$')) {
                throw new RuntimeException('Cannot dump definitions which have a variable class name.');
            }

            if (null !== $value->getFactoryMethod()) {
                if (null !== $value->getFactoryClass()) {
                    return sprintf("call_user_func(array(%s, '%s')%s)", $this->dumpValue($value->getFactoryClass()), $value->getFactoryMethod(), count($arguments) > 0 ? ', '.implode(', ', $arguments) : '');
                } elseif (null !== $value->getFactoryService()) {
                    return sprintf("%s->%s(%s)", $this->getServiceCall($value->getFactoryService()), $value->getFactoryMethod(), implode(', ', $arguments));
                } else {
                    throw new RuntimeException('Cannot dump definitions which have factory method without factory service or factory class.');
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
                $replaceParameters = function ($match) use ($that) {
                    return "'.".$that->dumpParameter(strtolower($match[2])).".'";
                };

                $code = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameters, var_export($value, true)));

                // optimize string
                $code = preg_replace(array("/^''\./", "/\.''$/", "/(\w+)(?:'\.')/", "/(.+)(?:\.''\.)/"), array('', '', '$1', '$1.'), $code);

                return $code;
            }
        } elseif (is_object($value) || is_resource($value)) {
            throw new RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        } else {
            return var_export($value, true);
        }
    }

    /**
     * Dumps a parameter
     *
     * @param string $name
     *
     * @return string
     */
    public function dumpParameter($name)
    {
        if ($this->container->isFrozen() && $this->container->hasParameter($name)) {
            return $this->dumpValue($this->container->getParameter($name), false);
        }

        return sprintf("\$this->getParameter('%s')", strtolower($name));
    }

    /**
     * Gets a service call
     *
     * @param string    $id
     * @param Reference $reference
     *
     * @return string
     */
    private function getServiceCall($id, Reference $reference = null)
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
    private function getNextVariableName()
    {
        $firstChars = self::FIRST_CHARS;
        $firstCharsLength = strlen($firstChars);
        $nonFirstChars = self::NON_FIRST_CHARS;
        $nonFirstCharsLength = strlen($nonFirstChars);

        while (true) {
            $name = '';
            $i = $this->variableCount;

            if ('' === $name) {
                $name .= $firstChars[$i%$firstCharsLength];
                $i = intval($i/$firstCharsLength);
            }

            while ($i > 0) {
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
