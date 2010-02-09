<?php

namespace Symfony\Components\DependencyInjection\Dumper;

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
      $this->addAnnotations().
      $this->addDefaultParametersMethod().
      $this->endClass()
    ;
  }

  protected function addServiceInclude($id, $definition)
  {
    if (null !== $definition->getFile())
    {
      return sprintf("    require_once %s;\n\n", $this->dumpValue($definition->getFile()));
    }
  }

  protected function addServiceShared($id, $definition)
  {
    if ($definition->isShared())
    {
      return <<<EOF
    if (isset(\$this->shared['$id'])) return \$this->shared['$id'];


EOF;
    }
  }

  protected function addServiceReturn($id, $definition)
  {
    if ($definition->isShared())
    {
      return <<<EOF

    return \$this->shared['$id'] = \$instance;
  }

EOF;
    }
    else
    {
      return <<<EOF

    return \$instance;
  }

EOF;
    }
  }

  protected function addServiceInstance($id, $definition)
  {
    $class = $this->dumpValue($definition->getClass());

    $arguments = array();
    foreach ($definition->getArguments() as $value)
    {
      $arguments[] = $this->dumpValue($value);
    }

    if (null !== $definition->getConstructor())
    {
      return sprintf("    \$instance = call_user_func(array(%s, '%s')%s);\n", $class, $definition->getConstructor(), $arguments ? ', '.implode(', ', $arguments) : '');
    }
    else
    {
      if ($class != "'".str_replace('\\', '\\\\', $definition->getClass())."'")
      {
        return sprintf("    \$class = %s;\n    \$instance = new \$class(%s);\n", $class, implode(', ', $arguments));
      }
      else
      {
        return sprintf("    \$instance = new %s(%s);\n", $definition->getClass(), implode(', ', $arguments));
      }
    }
  }

  protected function addServiceMethodCalls($id, $definition)
  {
    $calls = '';
    foreach ($definition->getMethodCalls() as $call)
    {
      $arguments = array();
      foreach ($call[1] as $value)
      {
        $arguments[] = $this->dumpValue($value);
      }

      $calls .= $this->wrapServiceConditionals($call[1], sprintf("    \$instance->%s(%s);\n", $call[0], implode(', ', $arguments)));
    }

    return $calls;
  }

  protected function addServiceConfigurator($id, $definition)
  {
    if (!$callable = $definition->getConfigurator())
    {
      return '';
    }

    if (is_array($callable))
    {
      if (is_object($callable[0]) && $callable[0] instanceof Reference)
      {
        return sprintf("    %s->%s(\$instance);\n", $this->getServiceCall((string) $callable[0]), $callable[1]);
      }
      else
      {
        return sprintf("    call_user_func(array(%s, '%s'), \$instance);\n", $this->dumpValue($callable[0]), $callable[1]);
      }
    }
    else
    {
      return sprintf("    %s(\$instance);\n", $callable);
    }
  }

  protected function addService($id, $definition)
  {
    $name = Container::camelize($id);
    $class = $definition->getClass();
    $type = 0 === strpos($class, '%') ? 'Object' : $class;

    $doc = '';
    if ($definition->isShared())
    {
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
   * @return $type A $class instance.
   */
  protected function get{$name}Service()
  {

EOF;

    $code .=
      $this->addServiceInclude($id, $definition).
      $this->addServiceShared($id, $definition).
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

    if ($this->container->hasDefinition($id))
    {
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
    foreach ($this->container->getDefinitions() as $id => $definition)
    {
      $code .= $this->addService($id, $definition);
    }

    foreach ($this->container->getAliases() as $alias => $id)
    {
      $code .= $this->addServiceAlias($alias, $id);
    }

    return $code;
  }

  protected function addAnnotations()
  {
    $annotations = array();
    foreach ($this->container->getDefinitions() as $id => $definition)
    {
      foreach ($definition->getAnnotations() as $name => $ann)
      {
        if (!isset($annotations[$name]))
        {
          $annotations[$name] = array();
        }

        $annotations[$name][$id] = $ann;
      }
    }
    $annotations = var_export($annotations, true);

    return <<<EOF

  /**
   * Returns service ids for a given annotation.
   *
   * @param string \$name The annotation name
   *
   * @return array An array of annotations
   */
  public function findAnnotatedServiceIds(\$name)
  {
    static \$annotations = $annotations;

    return isset(\$annotations[\$name]) ? \$annotations[\$name] : array();
  }

EOF;
  }

  protected function startClass($class, $baseClass)
  {
    $properties = array();
    foreach ($this->container->getDefinitions() as $id => $definition)
    {
      $type = 0 === strpos($definition->getClass(), '%') ? 'Object' : $definition->getClass();
      $properties[] = sprintf(' * @property %s $%s', $type, $id);
    }

    foreach ($this->container->getAliases() as $alias => $id)
    {
      $type = 'Object';
      if ($this->container->hasDefinition($id))
      {
        $sclass = $this->container->getDefinition($id)->getClass();
        $type = 0 === strpos($sclass, '%') ? 'Object' : $sclass;
      }

      $properties[] = sprintf(' * @property %s $%s', $type, $alias);
    }
    $properties = implode("\n", $properties);
    if ($properties)
    {
      $properties = "\n *\n".$properties;
    }

    return <<<EOF
<?php

use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.$properties
 */
class $class extends $baseClass
{
  protected \$shared = array();

EOF;
  }

  protected function addConstructor()
  {
    if (!$this->container->getParameters())
    {
      return '';
    }

    return <<<EOF

  /**
   * Constructor.
   */
  public function __construct()
  {
    parent::__construct();

    \$this->parameters = \$this->getDefaultParameters();
  }

EOF;
  }

  protected function addDefaultParametersMethod()
  {
    if (!$this->container->getParameters())
    {
      return '';
    }

    $parameters = $this->exportParameters($this->container->getParameters());

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

  protected function exportParameters($parameters, $indent = 6)
  {
    $php = array();
    foreach ($parameters as $key => $value)
    {
      if (is_array($value))
      {
        $value = $this->exportParameters($value, $indent + 2);
      }
      elseif ($value instanceof Reference)
      {
        throw new \InvalidArgumentException(sprintf('You cannot dump a container with parameters that contain references to other services (reference to service %s found).', $value));
      }
      else
      {
        $value = var_export($value, true);
      }

      $php[] = sprintf('%s%s => %s,', str_repeat(' ', $indent), var_export($key, true), $value);
    }

    return sprintf("array(\n%s\n%s)", implode("\n", $php), str_repeat(' ', $indent - 2));
  }

  protected function endClass()
  {
    return <<<EOF
}

EOF;
  }

  protected function wrapServiceConditionals($value, $code)
  {
    if (!$services = Builder::getServiceConditionals($value))
    {
      return $code;
    }

    $conditions = array();
    foreach ($services as $service)
    {
      $conditions[] = sprintf("\$this->hasService('%s')", $service);
    }

    // re-indent the wrapped code
    $code = implode("\n", array_map(function ($line) { return $line ? '  '.$line : $line; }, explode("\n", $code)));

    return sprintf("    if (%s)\n    {\n%s    }\n", implode(' && ', $conditions), $code);
  }

  protected function dumpValue($value)
  {
    if (is_array($value))
    {
      $code = array();
      foreach ($value as $k => $v)
      {
        $code[] = sprintf("%s => %s", $this->dumpValue($k), $this->dumpValue($v));
      }

      return sprintf("array(%s)", implode(', ', $code));
    }
    elseif (is_object($value) && $value instanceof Reference)
    {
      return $this->getServiceCall((string) $value, $value);
    }
    elseif (is_object($value) && $value instanceof Parameter)
    {
      return sprintf("\$this->getParameter('%s')", strtolower($value));
    }
    elseif (is_string($value))
    {
      if (preg_match('/^%([^%]+)%$/', $value, $match))
      {
        // we do this to deal with non string values (boolean, integer, ...)
        // the preg_replace_callback converts them to strings
        return sprintf("\$this->getParameter('%s')", strtolower($match[1]));
      }
      else
      {
        $replaceParameters = function ($match)
        {
          return sprintf("'.\$this->getParameter('%s').'", strtolower($match[2]));
        };

        $code = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameters, var_export($value, true)));

        // optimize string
        $code = preg_replace(array("/^''\./", "/\.''$/", "/\.''\./"), array('', '', '.'), $code);

        return $code;
      }
    }
    elseif (is_object($value) || is_resource($value))
    {
      throw new \RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
    }
    else
    {
      return var_export($value, true);
    }
  }

  protected function getServiceCall($id, Reference $reference = null)
  {
    if ('service_container' === $id)
    {
      return '$this';
    }

    if (null !== $reference && Container::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior())
    {
      return sprintf('$this->getService(\'%s\', Container::NULL_ON_INVALID_REFERENCE)', $id);
    }
    else
    {
      if ($this->container->hasAlias($id))
      {
        $id = $this->container->getAlias($id);
      }

      if ($this->container->hasDefinition($id))
      {
        return sprintf('$this->get%sService()', Container::camelize($id));
      }

      return sprintf('$this->getService(\'%s\')', $id);
    }
  }
}
