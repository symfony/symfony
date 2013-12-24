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
        $this->writeDocument($this->getRouteCollectionDocument($routes));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $this->writeDocument($this->getRouteDocument($route, isset($options['name']) ? $options['name'] : null));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $this->writeDocument($this->getContainerParametersDocument($parameters));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $this->writeDocument($this->getContainerTagsDocument($builder, isset($options['show_private']) && $options['show_private']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = array())
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        $this->writeDocument($this->getContainerServiceDocument($service, $options['id']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $this->writeDocument($this->getContainerServicesDocument($builder, isset($options['tag']) ? $options['tag'] : null, isset($options['show_private']) && $options['show_private']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $this->writeDocument($this->getContainerDefinitionDocument($definition, isset($options['id']) ? $options['id'] : null, isset($options['omit_tags']) && $options['omit_tags']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $this->writeDocument($this->getContainerAliasDocument($alias, isset($options['id']) ? $options['id'] : null));
    }

    /**
     * Writes DOM document.
     *
     * @param \DOMDocument $dom
     *
     * @return \DOMDocument|string
     */
    private function writeDocument(\DOMDocument $dom)
    {
        $dom->formatOutput = true;
        $this->write($dom->saveXML());
    }

    /**
     * @param RouteCollection $routes
     *
     * @return \DOMDocument
     */
    private function getRouteCollectionDocument(RouteCollection $routes)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routesXML = $dom->createElement('routes'));

        foreach ($routes->all() as $name => $route) {
            $routeXML = $this->getRouteDocument($route, $name);
            $routesXML->appendChild($routesXML->ownerDocument->importNode($routeXML->childNodes->item(0), true));
        }

        return $dom;
    }

    /**
     * @param Route       $route
     * @param string|null $name
     *
     * @return \DOMDocument
     */
    private function getRouteDocument(Route $route, $name = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routeXML = $dom->createElement('route'));

        if ($name) {
            $routeXML->setAttribute('name', $name);
        }

        $routeXML->setAttribute('class', get_class($route));

        $routeXML->appendChild($pathXML = $dom->createElement('path'));
        $pathXML->setAttribute('regex', $route->compile()->getRegex());
        $pathXML->appendChild(new \DOMText($route->getPath()));

        if ('' !== $route->getHost()) {
            $routeXML->appendChild($hostXML = $dom->createElement('host'));
            $hostXML->setAttribute('regex', $route->compile()->getHostRegex());
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
                $defaultXML->setAttribute('key', $attribute);
                $defaultXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);
        if (count($requirements)) {
            $routeXML->appendChild($requirementsXML = $dom->createElement('requirements'));
            foreach ($requirements as $attribute => $pattern) {
                $requirementsXML->appendChild($requirementXML = $dom->createElement('requirement'));
                $requirementXML->setAttribute('key', $attribute);
                $requirementXML->appendChild(new \DOMText($pattern));
            }
        }

        if (count($route->getOptions())) {
            $routeXML->appendChild($optionsXML = $dom->createElement('options'));
            foreach ($route->getOptions() as $name => $value) {
                $optionsXML->appendChild($optionXML = $dom->createElement('option'));
                $optionXML->setAttribute('key', $name);
                $optionXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        return $dom;
    }

    /**
     * @param ParameterBag $parameters
     *
     * @return \DOMDocument
     */
    private function getContainerParametersDocument(ParameterBag $parameters)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($parametersXML = $dom->createElement('parameters'));

        foreach ($this->sortParameters($parameters) as $key => $value) {
            $parametersXML->appendChild($parameterXML = $dom->createElement('parameter'));
            $parameterXML->setAttribute('key', $key);
            $parameterXML->appendChild(new \DOMText($this->formatParameter($value)));
        }

        return $dom;
    }

    /**
     * @param ContainerBuilder $builder
     * @param boolean          $showPrivate
     *
     * @return \DOMDocument
     */
    private function getContainerTagsDocument(ContainerBuilder $builder, $showPrivate = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $containerXML->appendChild($tagXML = $dom->createElement('tag'));
            $tagXML->setAttribute('name', $tag);

            foreach ($definitions as $serviceId => $definition) {
                $definitionXML = $this->getContainerDefinitionDocument($definition, $serviceId, true);
                $tagXML->appendChild($dom->importNode($definitionXML->childNodes->item(0), true));
            }
        }

        return $dom;
    }

    /**
     * @param mixed  $service
     * @param string $id
     *
     * @return \DOMDocument
     */
    private function getContainerServiceDocument($service, $id)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        if ($service instanceof Alias) {
            $dom->appendChild($dom->importNode($this->getContainerAliasDocument($service, $id)->childNodes->item(0), true));
        } elseif ($service instanceof Definition) {
            $dom->appendChild($dom->importNode($this->getContainerDefinitionDocument($service, $id)->childNodes->item(0), true));
        } else {
            $dom->appendChild($serviceXML = $dom->createElement('service'));
            $serviceXML->setAttribute('id', $id);
            $serviceXML->setAttribute('class', get_class($service));
        }

        return $dom;
    }

    /**
     * @param ContainerBuilder $builder
     * @param string|null      $tag
     * @param boolean          $showPrivate
     *
     * @return \DOMDocument
     */
    private function getContainerServicesDocument(ContainerBuilder $builder, $tag = null, $showPrivate = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        $serviceIds = $tag ? array_keys($builder->findTaggedServiceIds($tag)) : $builder->getServiceIds();

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Definition && !($showPrivate || $service->isPublic())) {
                continue;
            }

            $serviceXML = $this->getContainerServiceDocument($service, $serviceId);
            $containerXML->appendChild($containerXML->ownerDocument->importNode($serviceXML->childNodes->item(0), true));
        }

        return $dom;
    }

    /**
     * @param Definition  $definition
     * @param string|null $id
     * @param boolean     $omitTags
     *
     * @return \DOMDocument
     */
    private function getContainerDefinitionDocument(Definition $definition, $id = null, $omitTags = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($serviceXML = $dom->createElement('definition'));

        if ($id) {
            $serviceXML->setAttribute('id', $id);
        }

        $serviceXML->setAttribute('class', $definition->getClass());

        if ($definition->getFactoryClass()) {
            $serviceXML->setAttribute('factory-class', $definition->getFactoryClass());
        }

        if ($definition->getFactoryService()) {
            $serviceXML->setAttribute('factory-service', $definition->getFactoryService());
        }

        if ($definition->getFactoryMethod()) {
            $serviceXML->setAttribute('factory-method', $definition->getFactoryMethod());
        }

        $serviceXML->setAttribute('scope', $definition->getScope());
        $serviceXML->setAttribute('public', $definition->isPublic() ? 'true' : 'false');
        $serviceXML->setAttribute('synthetic', $definition->isSynthetic() ? 'true' : 'false');
        $serviceXML->setAttribute('lazy', $definition->isLazy() ? 'true' : 'false');
        $serviceXML->setAttribute('synchronized', $definition->isSynchronized() ? 'true' : 'false');
        $serviceXML->setAttribute('abstract', $definition->isAbstract() ? 'true' : 'false');
        $serviceXML->setAttribute('file', $definition->getFile());

        if (!$omitTags) {
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
                            $parameterXML->appendChild(new \DOMText($this->formatParameter($value)));
                        }
                    }
                }
            }
        }

        return $dom;
    }

    /**
     * @param Alias       $alias
     * @param string|null $id
     *
     * @return \DOMDocument
     */
    private function getContainerAliasDocument(Alias $alias, $id = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($aliasXML = $dom->createElement('alias'));

        if ($id) {
            $aliasXML->setAttribute('id', $id);
        }

        $aliasXML->setAttribute('service', (string) $alias);
        $aliasXML->setAttribute('public', $alias->isPublic() ? 'true' : 'false');

        return $dom;
    }
}
