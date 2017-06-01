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
 * Converts route definitions to PHP.
 *
 * @author David Tengeri <dtengeri@gmail.com>
 */
class PhpExporter extends AbstractExporter
{
    /**
     * {@inheritdoc}
     */
    public function exportRoutes(RouteCollection $routes)
    {
        // The routing php content.
        $routesPhp = <<<'EOF'
<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();

EOF;
        // Add the route definitions.
        foreach ($routes->all() as $name => $route) {
            $routesPhp .= <<<EOF
\$collection->add('$name', new Route(
    {$this->generateArgument($route->getPath())}, // path
    {$this->generateArgument($route->getDefaults())}, // defaults
    {$this->generateArgument($route->getRequirements())}, // requirements
    {$this->generateArgument($route->getOptions())}, // options
    {$this->generateArgument($route->getHost())}, // host
    {$this->generateArgument($route->getSchemes())}, // schemes
    {$this->generateArgument($route->getMethods())}, // methods
    {$this->generateArgument($route->getCondition())} // condition
));

EOF;
        }
        $routesPhp .= 'return $collection;';

        // Save the file.
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'routing.php', $routesPhp);
    }

    /**
     * Generates function argument in string format.
     *
     * @param object $argument The argument to convert to its source code string.
     *
     * @return string The PHP code representation of $argument.
     */
    private function generateArgument($argument)
    {
        return str_replace("\n", '', var_export($argument, true));
    }
} 