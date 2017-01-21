<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class MarkdownDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $first = true;
        foreach ($routes->all() as $name => $route) {
            if ($first) {
                $first = false;
            } else {
                $this->write("\n\n");
            }
            $this->describeRoute($route, array('name' => $name));
        }
        $this->write("\n");
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $output = '- Path: '.$route->getPath()
            ."\n".'- Path Regex: '.$route->compile()->getRegex()
            ."\n".'- Host: '.('' !== $route->getHost() ? $route->getHost() : 'ANY')
            ."\n".'- Host Regex: '.('' !== $route->getHost() ? $route->compile()->getHostRegex() : '')
            ."\n".'- Scheme: '.($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY')
            ."\n".'- Method: '.($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY')
            ."\n".'- Class: '.get_class($route)
            ."\n".'- Defaults: '.$this->formatRouterConfig($route->getDefaults())
            ."\n".'- Requirements: '.($route->getRequirements() ? $this->formatRouterConfig($route->getRequirements()) : 'NO CUSTOM')
            ."\n".'- Options: '.$this->formatRouterConfig($route->getOptions());

        $this->write(isset($options['name'])
            ? $options['name']."\n".str_repeat('-', strlen($options['name']))."\n\n".$output
            : $output);
        $this->write("\n");
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $this->write("Container parameters\n====================\n");
        foreach ($this->sortParameters($parameters) as $key => $value) {
            $this->write(sprintf("\n- `%s`: `%s`", $key, $this->formatParameter($value)));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $this->write("Container tags\n==============");

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $this->write("\n\n".$tag."\n".str_repeat('-', strlen($tag)));
            foreach ($definitions as $serviceId => $definition) {
                $this->write("\n\n");
                $this->describeContainerDefinition($definition, array('omit_tags' => true, 'id' => $serviceId));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = array())
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        $childOptions = array('id' => $options['id'], 'as_array' => true);

        if ($service instanceof Alias) {
            $this->describeContainerAlias($service, $childOptions);
        } elseif ($service instanceof Definition) {
            $this->describeContainerDefinition($service, $childOptions);
        } else {
            $this->write(sprintf('**`%s`:** `%s`', $options['id'], get_class($service)));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];

        $title = $showPrivate ? 'Public and private services' : 'Public services';
        if (isset($options['tag'])) {
            $title .= ' with tag `'.$options['tag'].'`';
        }
        $this->write($title."\n".str_repeat('=', strlen($title)));

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $services = array('definitions' => array(), 'aliases' => array(), 'services' => array());

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Alias) {
                $services['aliases'][$serviceId] = $service;
            } elseif ($service instanceof Definition) {
                if (($showPrivate || $service->isPublic())) {
                    $services['definitions'][$serviceId] = $service;
                }
            } else {
                $services['services'][$serviceId] = $service;
            }
        }

        if (!empty($services['definitions'])) {
            $this->write("\n\nDefinitions\n-----------\n");
            foreach ($services['definitions'] as $id => $service) {
                $this->write("\n");
                $this->describeContainerDefinition($service, array('id' => $id));
            }
        }

        if (!empty($services['aliases'])) {
            $this->write("\n\nAliases\n-------\n");
            foreach ($services['aliases'] as $id => $service) {
                $this->write("\n");
                $this->describeContainerAlias($service, array('id' => $id));
            }
        }

        if (!empty($services['services'])) {
            $this->write("\n\nServices\n--------\n");
            foreach ($services['services'] as $id => $service) {
                $this->write("\n");
                $this->write(sprintf('- `%s`: `%s`', $id, get_class($service)));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $output = '- Class: `'.$definition->getClass().'`'
            ."\n".'- Public: '.($definition->isPublic() ? 'yes' : 'no')
            ."\n".'- Synthetic: '.($definition->isSynthetic() ? 'yes' : 'no')
            ."\n".'- Lazy: '.($definition->isLazy() ? 'yes' : 'no')
        ;

        if (method_exists($definition, 'isShared')) {
            $output .= "\n".'- Shared: '.($definition->isShared() ? 'yes' : 'no');
        }

        $output .= "\n".'- Abstract: '.($definition->isAbstract() ? 'yes' : 'no');

        if (method_exists($definition, 'isAutowired')) {
            $output .= "\n".'- Autowired: '.($definition->isAutowired() ? 'yes' : 'no');

            foreach ($definition->getAutowiringTypes() as $autowiringType) {
                $output .= "\n".'- Autowiring Type: `'.$autowiringType.'`';
            }
        }

        if ($definition->getFile()) {
            $output .= "\n".'- File: `'.$definition->getFile().'`';
        }

        if ($factory = $definition->getFactory()) {
            if (is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $output .= "\n".'- Factory Service: `'.$factory[0].'`';
                } elseif ($factory[0] instanceof Definition) {
                    throw new \InvalidArgumentException('Factory is not describable.');
                } else {
                    $output .= "\n".'- Factory Class: `'.$factory[0].'`';
                }
                $output .= "\n".'- Factory Method: `'.$factory[1].'`';
            } else {
                $output .= "\n".'- Factory Function: `'.$factory.'`';
            }
        }

        $calls = $definition->getMethodCalls();
        foreach ($calls as $callData) {
            $output .= "\n".'- Call: `'.$callData[0].'`';
        }

        if (!(isset($options['omit_tags']) && $options['omit_tags'])) {
            foreach ($definition->getTags() as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $output .= "\n".'- Tag: `'.$tagName.'`';
                    foreach ($parameters as $name => $value) {
                        $output .= "\n".'    - '.ucfirst($name).': '.$value;
                    }
                }
            }
        }

        $this->write(isset($options['id']) ? sprintf("### %s\n\n%s\n", $options['id'], $output) : $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $output = '- Service: `'.$alias.'`'
            ."\n".'- Public: '.($alias->isPublic() ? 'yes' : 'no');

        $this->write(isset($options['id']) ? sprintf("### %s\n\n%s\n", $options['id'], $output) : $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameter($parameter, array $options = array())
    {
        $this->write(isset($options['parameter']) ? sprintf("%s\n%s\n\n%s", $options['parameter'], str_repeat('=', strlen($options['parameter'])), $this->formatParameter($parameter)) : $parameter);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = array())
    {
        $event = array_key_exists('event', $options) ? $options['event'] : null;

        $title = 'Registered listeners';
        if (null !== $event) {
            $title .= sprintf(' for event `%s` ordered by descending priority', $event);
        }

        $this->write(sprintf('# %s', $title)."\n");

        $registeredListeners = $eventDispatcher->getListeners($event);
        if (null !== $event) {
            foreach ($registeredListeners as $order => $listener) {
                $this->write("\n".sprintf('## Listener %d', $order + 1)."\n");
                $this->describeCallable($listener);
                $this->write(sprintf('- Priority: `%d`', $eventDispatcher->getListenerPriority($event, $listener))."\n");
            }
        } else {
            ksort($registeredListeners);

            foreach ($registeredListeners as $eventListened => $eventListeners) {
                $this->write("\n".sprintf('## %s', $eventListened)."\n");

                foreach ($eventListeners as $order => $eventListener) {
                    $this->write("\n".sprintf('### Listener %d', $order + 1)."\n");
                    $this->describeCallable($eventListener);
                    $this->write(sprintf('- Priority: `%d`', $eventDispatcher->getListenerPriority($eventListened, $eventListener))."\n");
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCallable($callable, array $options = array())
    {
        $string = '';

        if (is_array($callable)) {
            $string .= "\n- Type: `function`";

            if (is_object($callable[0])) {
                $string .= "\n".sprintf('- Name: `%s`', $callable[1]);
                $string .= "\n".sprintf('- Class: `%s`', get_class($callable[0]));
            } else {
                if (0 !== strpos($callable[1], 'parent::')) {
                    $string .= "\n".sprintf('- Name: `%s`', $callable[1]);
                    $string .= "\n".sprintf('- Class: `%s`', $callable[0]);
                    $string .= "\n- Static: yes";
                } else {
                    $string .= "\n".sprintf('- Name: `%s`', substr($callable[1], 8));
                    $string .= "\n".sprintf('- Class: `%s`', $callable[0]);
                    $string .= "\n- Static: yes";
                    $string .= "\n- Parent: yes";
                }
            }

            return $this->write($string."\n");
        }

        if (is_string($callable)) {
            $string .= "\n- Type: `function`";

            if (false === strpos($callable, '::')) {
                $string .= "\n".sprintf('- Name: `%s`', $callable);
            } else {
                $callableParts = explode('::', $callable);

                $string .= "\n".sprintf('- Name: `%s`', $callableParts[1]);
                $string .= "\n".sprintf('- Class: `%s`', $callableParts[0]);
                $string .= "\n- Static: yes";
            }

            return $this->write($string."\n");
        }

        if ($callable instanceof \Closure) {
            $string .= "\n- Type: `closure`";

            return $this->write($string."\n");
        }

        if (method_exists($callable, '__invoke')) {
            $string .= "\n- Type: `object`";
            $string .= "\n".sprintf('- Name: `%s`', get_class($callable));

            return $this->write($string."\n");
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }

    /**
     * @param array $array
     *
     * @return string
     */
    private function formatRouterConfig(array $array)
    {
        if (!count($array)) {
            return 'NONE';
        }

        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= "\n".'    - `'.$name.'`: '.$this->formatValue($value);
        }

        return $string;
    }
}
