<?php

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpDumper extends Dumper
{
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
            $this->addTags().
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
    protected function applyIntrefaceInjectors(\$instance)
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

    protected function addServiceInclude($id, $definition)
    {
        if (null !== $definition->getFile()) {
            return sprintf("        require_once %s;\n\n", $this->dumpValue($definition->getFile()));
        }
    }

    protected function addServiceReturn($id, $definition)
    {
        if (!$definition->getMethodCalls() && !$definition->getConfigurator()) {
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

        $simple = !$definition->getMethodCalls() && !$definition->getConfigurator();

        $instantiation = '';
        if ($definition->isShared()) {
            $instantiation = "\$this->services['$id'] = ".($simple ? '' : '$instance');
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

        return $code;
    }

    protected function addServiceMethodCalls($id, $definition)
    {
        $calls = '';
        foreach ($definition->getMethodCalls() as $call) {
            $arguments = array();
            foreach ($call[1] as $value) {
                $arguments[] = $this->dumpValue($value);
            }

            $calls .= $this->wrapServiceConditionals($call[1], sprintf("        \$instance->%s(%s);\n", $call[0], implode(', ', $arguments)));
        }

        if (!$this->container->isFrozen() && count($this->container->getInterfaceInjectors()) > 0) {
            $calls = sprintf("\n        \$this->applyInterfaceInjection(\$instance);\n");
        }

        return $calls;
    }

    protected function addServiceConfigurator($id, $definition)
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (is_array($callable)) {
            if (is_object($callable[0]) && $callable[0] instanceof Reference) {
                return sprintf("        %s->%s(\$instance);\n", $this->getServiceCall((string) $callable[0]), $callable[1]);
            } else {
                return sprintf("        call_user_func(array(%s, '%s'), \$instance);\n", $this->dumpValue($callable[0]), $callable[1]);
            }
        } else {
            return sprintf("        %s(\$instance);\n", $callable);
        }
    }

    protected function addService($id, $definition)
    {
        $name = Container::camelize($id);

        $return = '';
        if ($class = $definition->getClass()) {
            $return = sprintf("@return %s A %s instance.", 0 === strpos($class, '%') ? 'Object' : $class, $class);
        } elseif ($definition->getFactoryService()) {
            $return = sprintf('@return Object An instance returned by %s::%s().', $definition->getFactoryService(), $definition->getFactoryMethod());
        }

        $doc = '';
        if ($definition->isShared()) {
            $doc = <<<EOF

     *
     * This service is shared.
     * This method always returns the same instance of the service.
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

        $code .=
            $this->addServiceInclude($id, $definition).
            $this->addServiceInstance($id, $definition).
            $this->addServiceMethodCalls($id, $definition).
            $this->addServiceConfigurator($id, $definition).
            $this->addServiceReturn($id, $definition)
        ;

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
        $code = '';
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $code .= $this->addService($id, $definition);
        }

        foreach ($this->container->getAliases() as $alias => $id) {
            $code .= $this->addServiceAlias($alias, $id);
        }

        return $code;
    }

    protected function addTags()
    {
        $tags = array();
        foreach ($this->container->getDefinitions() as $id => $definition) {
            foreach ($definition->getTags() as $name => $ann) {
                if (!isset($tags[$name])) {
                    $tags[$name] = array();
                }

                $tags[$name][$id] = $ann;
            }
        }
        $tags = $this->exportParameters($tags);

        return <<<EOF

    /**
     * Returns service ids for a given tag.
     *
     * @param string \$name The tag name
     *
     * @return array An array of tags
     */
    public function findTaggedServiceIds(\$name)
    {
        static \$tags = $tags;

        return isset(\$tags[\$name]) ? \$tags[\$name] : array();
    }

EOF;
    }

    protected function startClass($class, $baseClass)
    {
        $bagClass = $this->container->isFrozen() ? 'FrozenParameterBag' : 'ParameterBag';

        return <<<EOF
<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;
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
class $class extends $baseClass implements TaggedContainerInterface
{
EOF;
    }

    protected function addConstructor()
    {
        $bagClass = $this->container->isFrozen() ? 'FrozenParameterBag' : 'ParameterBag';

        return <<<EOF

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(new $bagClass(\$this->getDefaultParameters()));
    }

EOF;
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

    protected function dumpValue($value, $interpolate = true)
    {
        if (is_array($value)) {
            $code = array();
            foreach ($value as $k => $v) {
                $code[] = sprintf('%s => %s', $this->dumpValue($k, $interpolate), $this->dumpValue($v, $interpolate));
            }

            return sprintf('array(%s)', implode(', ', $code));
        } elseif (is_object($value) && $value instanceof Reference) {
            return $this->getServiceCall((string) $value, $value);
        } elseif (is_object($value) && $value instanceof Parameter) {
            return $this->dumpParameter($value);
        } elseif (true === $interpolate && is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (boolean, integer, ...)
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
                $code = preg_replace(array("/^''\./", "/\.''$/", "/\.''\./"), array('', '', '.'), $code);

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
                $id = $this->container->getAlias($id);
            }

            return sprintf('$this->get(\'%s\')', $id);
        }
    }
}
