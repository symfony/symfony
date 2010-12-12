<?php

namespace Symfony\Component\Routing\Generator\Dumper;

use Symfony\Component\Routing\Route;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpGeneratorDumper creates a PHP class able to generate URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpGeneratorDumper extends GeneratorDumper
{
    /**
     * Dumps a set of routes to a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param  array  $options An array of options
     *
     * @return string A PHP class representing the generator class
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => 'ProjectUrlGenerator',
            'base_class' => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
        ), $options);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addGenerator().
            $this->endClass()
        ;
    }

    protected function addGenerator()
    {
        $methods = array();
        foreach ($this->routes->all() as $name => $route) {
            $compiledRoute = $route->compile();

            $variables = str_replace("\n", '', var_export($compiledRoute->getVariables(), true));
            $defaults = str_replace("\n", '', var_export($route->getDefaults(), true));
            $requirements = str_replace("\n", '', var_export($compiledRoute->getRequirements(), true));
            $tokens = str_replace("\n", '', var_export($compiledRoute->getTokens(), true));

            $escapedName = str_replace('.', '__', $name);

            $methods[] = <<<EOF
    protected function get{$escapedName}RouteInfo()
    {
        return array($variables, array_merge(\$this->defaults, $defaults), $requirements, $tokens);
    }

EOF
            ;
        }

        $methods = implode("\n", $methods);

        return <<<EOF

    public function generate(\$name, array \$parameters, \$absolute = false)
    {
        if (!isset(self::\$declaredRouteNames[\$name])) {
            throw new \InvalidArgumentException(sprintf('Route "%s" does not exist.', \$name));
        }

        \$escapedName = str_replace('.', '__', \$name);

        list(\$variables, \$defaults, \$requirements, \$tokens) = \$this->{'get'.\$escapedName.'RouteInfo'}();

        return \$this->doGenerate(\$variables, \$defaults, \$requirements, \$tokens, \$parameters, \$name, \$absolute);
    }

$methods
EOF;
    }

    protected function startClass($class, $baseClass)
    {
        $routes = array();
        foreach ($this->routes->all() as $name => $route) {
            $routes[] = "       '$name' => true,";
        }
        $routes  = implode("\n", $routes);

        return <<<EOF
<?php

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class $class extends $baseClass
{
    static protected \$declaredRouteNames = array(
$routes
    );


EOF;
    }

    protected function addConstructor()
    {
        return <<<EOF
    /**
     * Constructor.
     */
    public function __construct(array \$context = array(), array \$defaults = array())
    {
        \$this->context = \$context;
        \$this->defaults = \$defaults;
    }

EOF;
    }

    protected function endClass()
    {
        return <<<EOF
}

EOF;
    }
}
