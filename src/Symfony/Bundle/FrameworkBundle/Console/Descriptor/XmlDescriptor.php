<?php

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class XmlDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routesXML = $dom->createElement('routes'));

        foreach ($routes->all() as $name => $route) {
            $routeXML = $this->describeRoute($route, array('as_dom' => true, 'name' => $name));
            $routesXML->appendChild($routesXML->ownerDocument->importNode($routeXML->childNodes->item(0), true));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routeXML = $dom->createElement('route'));

        if (isset($options['name'])) {
            $routeXML->setAttribute('name', $options['name']);
        }

        $routeXML->setAttribute('path', $route->getPath());
        $routeXML->setAttribute('class', get_class($route));
        $routeXML->setAttribute('path_regex', $route->compile()->getRegex());

        if ('' !== $route->getHost()) {
            $routeXML->appendChild($hostXML = $dom->createElement('host'));
            $hostXML->appendChild(new \DOMText($route->getHost()));
        }

        foreach ($route->getSchemes() as $scheme) {
            $routeXML->appendChild($schemeXML = $dom->createElement('scheme'));
            $schemeXML->appendChild(new \DOMText($scheme));
        }

        foreach ($route->getMethods() as $method) {
            $routeXML->appendChild($methodXML = $dom->createElement('method'));
            $methodXML->appendChild(new \DOMText($method));
        }

        if (count($route->getDefaults())) {
            $routeXML->appendChild($defaultsXML = $dom->createElement('defaults'));
            foreach ($route->getDefaults() as $attribute => $value) {
                $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                $defaultXML->setAttribute('attribute', $attribute);
                $defaultXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);
        if (count($requirements)) {
            $routeXML->appendChild($requirementsXML = $dom->createElement('requirements'));
            foreach ($route->getOptions() as $attribute => $pattern) {
                $requirementsXML->appendChild($requirementXML = $dom->createElement('requirement'));
                $requirementXML->setAttribute('attribute', $attribute);
                $requirementXML->appendChild(new \DOMText($pattern));
            }
        }

        if (count($route->getOptions())) {
            $routeXML->appendChild($optionsXML = $dom->createElement('options'));
            foreach ($route->getOptions() as $name => $value) {
                $optionsXML->appendChild($optionXML = $dom->createElement('option'));
                $optionXML->setAttribute('name', $name);
                $optionXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($parametersXML = $dom->createElement('parameters'));

        foreach ($this->sortParameters($parameters) as $key => $value) {
            $parametersXML->appendChild($parameterXML = $dom->createElement('parameter'));
            $parameterXML->setAttribute('key', $key);
            $parameterXML->appendChild(new \DOMText($this->formatParameter($value)));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        $showPrivate = isset($options['show_private']) && $options['show_private'];

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $containerXML->appendChild($tagXML = $dom->createElement('tag'));
            $tagXML->setAttribute('name', $tag);

            foreach ($definitions as $serviceId => $definition) {
                $definitionXML = $this->describeContainerDefinition($definition, array('as_dom' => true, 'id' => $serviceId, 'omit_tags' => true));
                $tagXML->appendChild($dom->importNode($definitionXML->childNodes->item(0), true));
            }
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = array())
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $childOptions = array('id' => $options['id'], 'as_dom' => true);

        if ($service instanceof Alias) {
            $dom->appendChild($dom->importNode($this->describeContainerAlias($service, $childOptions)->childNodes->item(0), true));
        } elseif ($service instanceof Definition) {
            $dom->appendChild($dom->importNode($this->describeContainerDefinition($service, $childOptions)->childNodes->item(0), true));
        } else {
            $dom->appendChild($serviceXML = $dom->createElement('service'));
            $serviceXML->setAttribute('id', $options['id']);
            $serviceXML->setAttribute('class', get_class($service));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Definition && !($showPrivate || $service->isPublic())) {
                continue;
            }

            $serviceXML = $this->describeContainerService($service, array_merge($options, array('id' => $serviceId, 'as_dom' => true)));
            $containerXML->appendChild($containerXML->ownerDocument->importNode($serviceXML->childNodes->item(0), true));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($serviceXML = $dom->createElement('definition'));

        if (isset($options['id'])) {
            $serviceXML->setAttribute('id', $options['id']);
        }

        $serviceXML->setAttribute('class', $definition->getClass());
        $serviceXML->setAttribute('scope', $definition->getScope());
        $serviceXML->setAttribute('public', $definition->isPublic() ? 'true' : 'false');
        $serviceXML->setAttribute('synthetic', $definition->isSynthetic() ? 'true' : 'false');
        $serviceXML->setAttribute('file', $definition->getFile());

        if (!(isset($options['omit_tags']) && $options['omit_tags'])) {
            $tags = $definition->getTags();
            
            if (count($tags) > 0) {
                $serviceXML->appendChild($tagsXML = $dom->createElement('tags'));
                foreach ($tags as $tagName => $tagData) {
                    foreach ($tagData as $parameters) {
                        $tagsXML->appendChild($tagXML = $dom->createElement('tag'));
                        $tagXML->setAttribute('name', $tagName);
                        foreach ($parameters as $name => $value) {
                            $tagXML->appendChild($parameterXML = $dom->createElement('parameter'));
                            $parameterXML->setAttribute('name', $name);
                            $parameterXML->textContent = $value;
                        }
                    }
                }
            }
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($aliasXML = $dom->createElement('alias'));

        if (isset($options['id'])) {
            $aliasXML->setAttribute('id', $options['id']);
        }

        $aliasXML->setAttribute('service', (string) $alias);
        $aliasXML->setAttribute('public', $alias->isPublic() ? 'true' : 'false');

        return $this->output($dom, $options);
    }

    /**
     * Outputs document as DOMDocument or string according to options.
     *
     * @param \DOMDocument $dom
     * @param array        $options
     *
     * @return \DOMDocument|string
     */
    private function output(\DOMDocument $dom, array $options)
    {
        if (isset($options['as_dom']) && $options['as_dom']) {
            return $dom;
        }

        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
