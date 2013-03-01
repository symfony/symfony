<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * PhpMatcherDumper creates a PHP class able to match URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class PhpMatcherDumper extends MatcherDumper
{
    /**
     * Dumps a set of routes to a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param array $options An array of options
     *
     * @return string A PHP class representing the matcher class
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => 'ProjectUrlMatcher',
            'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
        ), $options);

        // trailing slash support is only enabled if we know how to redirect the user
        $interfaces = class_implements($options['base_class']);
        $supportsRedirections = isset($interfaces['Symfony\\Component\\Routing\\Matcher\\RedirectableUrlMatcherInterface']);

        return <<<EOF
<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * {$options['class']}
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class {$options['class']} extends {$options['base_class']}
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext \$context)
    {
        \$this->context = \$context;
    }

{$this->generateMatchMethod($supportsRedirections)}
}

EOF;
    }

    /**
     * Generates the code for the match method implementing UrlMatcherInterface.
     *
     * @param Boolean $supportsRedirections Whether redirections are supported by the base class
     *
     * @return string Match method as PHP code
     */
    private function generateMatchMethod($supportsRedirections)
    {
        $code = rtrim($this->compileRoutes($this->getRoutes(), $supportsRedirections), "\n");

        return <<<EOF
    public function match(\$pathinfo)
    {
        \$allow = array();
        \$pathinfo = rawurldecode(\$pathinfo);

$code

        throw 0 < count(\$allow) ? new MethodNotAllowedException(array_unique(\$allow)) : new ResourceNotFoundException();
    }
EOF;
    }

    /**
     * Counts the number of routes as direct child of the RouteCollection.
     *
     * @param RouteCollection $routes A RouteCollection instance
     *
     * @return integer Number of Routes
     */
    private function countDirectChildRoutes(RouteCollection $routes)
    {
        $count = 0;
        foreach ($routes as $route) {
            if ($route instanceof Route) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Generates PHP code recursively to match a RouteCollection with all child routes and child collections.
     *
     * @param RouteCollection $routes               A RouteCollection instance
     * @param Boolean         $supportsRedirections Whether redirections are supported by the base class
     * @param string|null     $parentPrefix         The prefix of the parent collection used to optimize the code
     *
     * @return string PHP code
     */
    private function compileRoutes(RouteCollection $routes, $supportsRedirections, $parentPrefix = null)
    {
        $code = '';

        $prefix = $routes->getPrefix();
        $countDirectChildRoutes = $this->countDirectChildRoutes($routes);
        $countAllChildRoutes = count($routes->all());
        // Can the matching be optimized by wrapping it with the prefix condition
        // - no need to optimize if current prefix is the same as the parent prefix
        // - if $countDirectChildRoutes === 0, the sub-collections can do their own optimizations (in case there are any)
        // - it's not worth wrapping a single child route
        // - prefixes with variables cannot be optimized because routes within the collection might have different requirements for the same variable
        $optimizable = '' !== $prefix && $prefix !== $parentPrefix && $countDirectChildRoutes > 0 && $countAllChildRoutes > 1 && false === strpos($prefix, '{');
        if ($optimizable) {
            $code .= sprintf("    if (0 === strpos(\$pathinfo, %s)) {\n", var_export($prefix, true));
        }

        foreach ($routes as $name => $route) {
            if ($route instanceof Route) {
                // a single route in a sub-collection is not wrapped so it should do its own optimization in ->compileRoute with $parentPrefix = null
                $code .= $this->compileRoute($route, $name, $supportsRedirections, 1 === $countAllChildRoutes ? null : $prefix)."\n";
            } elseif ($countAllChildRoutes - $countDirectChildRoutes > 0) { // we can stop iterating recursively if we already know there are no more routes
                $code .= $this->compileRoutes($route, $supportsRedirections, $prefix);
            }
        }

        if ($optimizable) {
            $code .= "    }\n\n";
            // apply extra indention at each line (except empty ones)
            $code = preg_replace('/^.{2,}$/m', '    $0', $code);
        }

        return $code;
    }


    /**
     * Compiles a single Route to PHP code used to match it against the path info.
     *
     * @param Route       $route                A Route instance
     * @param string      $name                 The name of the Route
     * @param Boolean     $supportsRedirections Whether redirections are supported by the base class
     * @param string|null $parentPrefix         The prefix of the parent collection used to optimize the code
     *
     * @return string PHP code
     */
    private function compileRoute(Route $route, $name, $supportsRedirections, $parentPrefix = null)
    {
        $code = '';
        $compiledRoute = $route->compile();
        $conditions = array();
        $hasTrailingSlash = false;
        $matches = false;
        $methods = array();

        if ($req = $route->getRequirement('_method')) {
            $methods = explode('|', strtoupper($req));
            // GET and HEAD are equivalent
            if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
                $methods[] = 'HEAD';
            }
        }

        $supportsTrailingSlash = $supportsRedirections && (!$methods || in_array('HEAD', $methods));

        if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', $compiledRoute->getRegex(), $m)) {
            if ($supportsTrailingSlash && substr($m['url'], -1) === '/') {
                $conditions[] = sprintf("rtrim(\$pathinfo, '/') === %s", var_export(rtrim(str_replace('\\', '', $m['url']), '/'), true));
                $hasTrailingSlash = true;
            } else {
                $conditions[] = sprintf("\$pathinfo === %s", var_export(str_replace('\\', '', $m['url']), true));
            }
        } else {
            if ($compiledRoute->getStaticPrefix() && $compiledRoute->getStaticPrefix() !== $parentPrefix) {
                $conditions[] = sprintf("0 === strpos(\$pathinfo, %s)", var_export($compiledRoute->getStaticPrefix(), true));
            }

            $regex = $compiledRoute->getRegex();
            if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
                $hasTrailingSlash = true;
            }
            $conditions[] = sprintf("preg_match(%s, \$pathinfo, \$matches)", var_export($regex, true));

            $matches = true;
        }

        $conditions = implode(' && ', $conditions);

        $code .= <<<EOF
        // $name
        if ($conditions) {

EOF;

        if ($methods) {
            $gotoname = 'not_'.preg_replace('/[^A-Za-z0-9_]/', '', $name);

            if (1 === count($methods)) {
                $code .= <<<EOF
            if (\$this->context->getMethod() != '$methods[0]') {
                \$allow[] = '$methods[0]';
                goto $gotoname;
            }


EOF;
            } else {
                $methods = implode("', '", $methods);
                $code .= <<<EOF
            if (!in_array(\$this->context->getMethod(), array('$methods'))) {
                \$allow = array_merge(\$allow, array('$methods'));
                goto $gotoname;
            }


EOF;
            }
        }

        if ($hasTrailingSlash) {
            $code .= <<<EOF
            if (substr(\$pathinfo, -1) !== '/') {
                return \$this->redirect(\$pathinfo.'/', '$name');
            }


EOF;
        }

        if ($scheme = $route->getRequirement('_scheme')) {
            if (!$supportsRedirections) {
                throw new \LogicException('The "_scheme" requirement is only supported for URL matchers that implement RedirectableUrlMatcherInterface.');
            }

            $code .= <<<EOF
            if (\$this->context->getScheme() !== '$scheme') {
                return \$this->redirect(\$pathinfo, '$name', '$scheme');
            }


EOF;
        }

        // optimize parameters array
        if (true === $matches && $route->getDefaults()) {
            $code .= sprintf("            return array_merge(\$this->mergeDefaults(\$matches, %s), array('_route' => '%s'));\n"
                , str_replace("\n", '', var_export($route->getDefaults(), true)), $name);
        } elseif (true === $matches) {
            $code .= sprintf("            \$matches['_route'] = '%s';\n\n", $name);
            $code .= "            return \$matches;\n";
        } elseif ($route->getDefaults()) {
            $code .= sprintf("            return %s;\n", str_replace("\n", '', var_export(array_merge($route->getDefaults(), array('_route' => $name)), true)));
        } else {
            $code .= sprintf("            return array('_route' => '%s');\n", $name);
        }
        $code .= "        }\n";

        if ($methods) {
            $code .= "        $gotoname:\n";
        }

        return $code;
    }
}
