<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exporter\Driver;
use Symfony\Component\Routing\RouteCollection;

/**
 * Converts route definitions to XML.
 *
 * @author David Tengeri <dtengeri@gmail.com>
 */
class XmlExporter extends AbstractExporter
{
    /**
     * {@inheritdoc}
     */
    public function exportRoutes(RouteCollection $routes)
    {
        // Create the root element.
        $routesXml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8" ?>' .
            '<routes xmlns="http://symfony.com/schema/routing"' .
                'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
                'xsi:schemaLocation="http://symfony.com/schema/routing ' .
                'http://symfony.com/schema/routing/routing-1.0.xsd" />');

        foreach ($routes->all() as $name => $route) {
            $routeXml = $routesXml->addChild('route');

            $routeXml->addAttribute('id', $name);
            $routeXml->addAttribute('path', $route->getPath());

            foreach ($route->getDefaults() as $key => $value) {
                $defaultXml = $routeXml->addChild('default', $value);
                $defaultXml->addAttribute('key', $key);
            }

            if (count($route->getRequirements())) {
                foreach ($route->getRequirements() as $key => $value) {
                    $requirementXml = $routeXml->addChild('requirement', $value);
                    $requirementXml->addAttribute('key', $key);
                }
            }

            if (count($route->getOptions())) {
                foreach ($route->getOptions() as $key => $value) {
                    $optionXml = $routeXml->addChild('option', $value);
                    $optionXml->addAttribute('key', $key);
                }
            }

            if ($route->getHost()) {
                $routeXml->addAttribute('host', $route->getHost());
            }

            if (count($route->getSchemes())) {
                $routeXml->addAttribute('schemes', implode(', ', $route->getSchemes()));
            }

            if (count($route->getMethods())) {
                $routeXml->addAttribute('methods', implode(', ', $route->getMethods()));
            }

            if ($route->getCondition()) {
                $routeXml->addChild('condition', $route->getCondition());
            }
        }

        // Save the route definitions as XML.
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($routesXml->asXML());
        $dom->formatOutput = true;

        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'routing.xml', $dom->saveXML());
    }
} 