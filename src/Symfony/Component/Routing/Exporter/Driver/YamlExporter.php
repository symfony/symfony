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
use Symfony\Component\Yaml\Yaml;

/**
 * Converts route definitions to YAML.
 *
 * @author David Tengeri <dtengeri@gmail.com>
 */
class YamlExporter extends AbstractExporter
{
    /**
     * {@inheritdoc}
     */
    public function exportRoutes(RouteCollection $routes)
    {
        // The route definitions.
        $definitions = array();
        foreach ($routes->all() as $name => $route) {
            $definitions[$name] = array(
                'path' => $route->getPath(),
                'defaults' => $route->getDefaults(),
            );

            if (count($route->getRequirements())) {
                $definitions[$name]['requirements'] = $route->getRequirements();
            }

            if (count($route->getOptions())) {
                $definitions[$name]['options'] = $route->getOptions();
            }

            if ($route->getHost()) {
                $definitions[$name]['host'] = $route->getHost();
            }

            if (count($route->getSchemes())) {
                $definitions[$name]['schemes'] = $route->getSchemes();
            }

            if (count($route->getMethods())) {
                $definitions[$name]['methods'] = $route->getMethods();
            }

            if ($route->getCondition()) {
                $definitions[$name]['condition'] = $route->getCondition();
            }

        }

        // Save the definitions as yml.
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'routing.yml', Yaml::dump($definitions));
    }

} 