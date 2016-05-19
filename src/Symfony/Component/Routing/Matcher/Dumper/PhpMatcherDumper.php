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

use Symfony\Component\Routing\CompiledRoute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\Yaml\Dumper;

/**
 * PhpMatcherDumper creates a PHP class able to match URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class PhpMatcherDumper extends MatcherDumper
{
    private $expressionLanguage;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = array();

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
        $options = array_replace(array(
            'class' => 'ProjectUrlMatcher',
            'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
        ), $options);

        // trailing slash support is only enabled if we know how to redirect the user
        $interfaces = class_implements($options['base_class']);
        $supportsRedirections = isset(
            $interfaces['Symfony\\Component\\Routing\\Matcher\\RedirectableUrlMatcherInterface']
        );

        $this->addPriorityToRoutes($this->getRoutes());

        $staticRoutes = $this->getStaticRoutes($this->getRoutes());
        $staticRoutesExport = var_export($staticRoutes, true);

        // apply extra indention at each line (except empty ones)
        $staticRoutesExport = preg_replace('/^.{2,}$/m', '        $0', $staticRoutesExport);

        return <<<EOF
<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * {$options['class']}.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class {$options['class']} extends {$options['base_class']}
{

    private \$staticRoutes;

    /**
     * Constructor.
     */
    public function __construct(RequestContext \$context)
    {
        \$this->context = \$context;
        \$this->staticRoutes = $staticRoutesExport;;
    }

{$this->generateMatchMethod()}
{$this->generateStaticMatchMethod($supportsRedirections)}
{$this->generateDynamicMatchMethod($supportsRedirections)}
}

EOF;
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * Generates the code for the match method implementing UrlMatcherInterface.
     *
     * @return string Match method as PHP code
     */
    private function generateMatchMethod()
    {
        return <<<EOF
    public function match(\$pathinfo)
    {
        \$allow = array();
        \$pathinfo = rawurldecode(\$pathinfo);
        \$staticMatch = \$this->staticMatch(\$pathinfo, \$allow);
        \$dynamicMatch = \$this->dynamicMatch(\$pathinfo, \$allow, \$staticMatch ? \$staticMatch['priority'] : null);

        // We don't need to compare the priorities
        // Because dynamicMatch returns a route only if
        // no static route have been found with a lowest priority
        if (null !== \$dynamicMatch) {
            return \$dynamicMatch['options'];
        }
        if (null !== \$staticMatch) {
            return \$staticMatch['options'];
        }

        throw (\$allow !== array()) ?
            new MethodNotAllowedException(array_unique(\$allow))
            :
            new ResourceNotFoundException();
    }
EOF;
    }

    /**
     * Generates the code for the match method implementing UrlMatcherInterface.
     *
     * @param bool $supportsRedirections Whether redirections are supported by the base class
     *
     * @return string DynamicMatch method as PHP code
     */
    private function generateDynamicMatchMethod($supportsRedirections)
    {
        $dynamicRoutes = $this->getDynamicRoutes($this->getRoutes());

        $code = rtrim($this->compileRoutes($dynamicRoutes, $supportsRedirections), "\n");

        return <<<EOF
    private function dynamicMatch(\$pathinfo, &\$allow = array(), \$maxPriority = null)
    {
        \$context = \$this->context;
        \$request = \$this->request;

$code
        return null;
    }
EOF;
    }

    /**
     * @param $supportsRedirections
     * @return string
     */
    private function generateStaticMatchMethod($supportsRedirections)
    {
        return <<<EOF
    private function staticMatch(\$pathinfo, &\$allow = array())
    {
        \$context = \$this->context;
        \$request = \$this->request;
        \$staticRoutes = \$this->staticRoutes;

        \$trimmedPathInfo = rtrim(\$pathinfo, '/');

        if (isset(\$staticRoutes[\$trimmedPathInfo])) {
            \$routes = \$staticRoutes[\$trimmedPathInfo];
            foreach (\$routes as \$route) {
                if (
                    (\$route['method'] === array() || in_array(\$request->getMethod(), \$route['method']))
                    &&
                    (\$route['host'] === null || preg_match(\$route['host'], \$context->getHost()))
                ) {
                    // If both are equals ==> pathinfo doesn't have a trailing slash
                    if (\$trimmedPathInfo === \$pathinfo && \$route['forceSlash']) {
                       // Will be if (false) or if (true) ==> OpCode Optimizer will remove useless code
                       if ($supportsRedirections) {
                           return array(
                               'priority' => \$route['priority'],
                               'options' => \$this->redirect(\$pathinfo . '/', \$route['options']['_route'])
                           );
                       } else {
                           return null;
                       }
                    }
                    return \$route;
                }
                if (\$route['method'] !== array() && !in_array(\$request->getMethod(), \$route['method'])) {
                    \$allow = array_merge(\$allow, \$route['method']);
                }
            }
        }

        return null;
    }
EOF;
    }

    /**
     * This method adds a "priority" on the routes.
     * Lowest numbers should be treated first
     *
     * @param RouteCollection $routes
     */
    private function addPriorityToRoutes(RouteCollection $routes)
    {
        $priority = 0;
        foreach ($routes as $route) {
            /**
             * @var $route Route
             */
            $route->setOption('priority', $priority++);
        }
    }

    /**
     * @param RouteCollection $routes all routes of the project
     * @return array an extract of $routes with only static (= no regex) routes
     */
    private function getStaticRoutes(RouteCollection $routes)
    {
        $return = array();
        /**
         * @var $route Route
         */
        foreach ($routes as $name => $route) {
            /**
             * @var $compiledRoute CompiledRoute
             */
            $compiledRoute = $route->compile();

            if (!count($compiledRoute->getPathVariables())) {
                $url = rtrim($compiledRoute->getStaticPrefix(), '/');
                if (false === isset($return[$url])) {
                    $return[$url] = array();
                }

                $return[$url][$route->getOption('priority')] = array(
                    'host' => $compiledRoute->getHostRegex(),
                    'method' => $route->getMethods(),
                    'priority' => $route->getOption('priority'),
                    'forceSlash' => substr($compiledRoute->getStaticPrefix(), -1) === '/' ? true:false,
                    'options' => ($route->getDefaults() ? array_replace($route->getDefaults(), array('_route' => $name)) : array('_route' => $name))
                );
                ksort($return[$url]);
            }
        }
        return $return;
    }

    /**
     * @param RouteCollection $routes all routes from the project
     * @return RouteCollection a collection containing only dynamic (= regex) routes
     */
    private function getDynamicRoutes(RouteCollection $routes)
    {
        $return = clone $routes;

        /**
         * @var $route Route
         */
        foreach ($return as $name => $route) {
            $compiledRoute = $route->compile();
            if (!count($compiledRoute->getPathVariables())) {
                $return->remove($name);
            }
        }
        return $return;
    }

    /**
     * Generates PHP code to match a RouteCollection with all its routes.
     *
     * @param RouteCollection $routes               A RouteCollection instance
     * @param bool            $supportsRedirections Whether redirections are supported by the base class
     *
     * @return string PHP code
     */
    private function compileRoutes(RouteCollection $routes, $supportsRedirections)
    {
        $fetchedHost = false;
        $groups = $this->groupRoutesByHostRegex($routes);
        $code = '';

        /**
         * @var $collection DumperCollection
         */
        foreach ($groups as $collection) {
            if (null !== $regex = $collection->getAttribute('host_regex')) {
                if (!$fetchedHost) {
                    $code .= "        \$host = \$this->context->getHost();\n\n";
                    $fetchedHost = true;
                }

                $code .= sprintf("        if (preg_match(%s, \$host, \$hostMatches)) {\n", var_export($regex, true));
            }

            $groupCode = '';
            $routes = [];

            foreach ($collection as $route) {
                $routes[] = $route;
                if (count($routes) % 10 === 0 && count($routes) > 0) {
                    $groupCode .= $this->dumpRoutesGroupedByRegex($routes, $supportsRedirections);
                    $routes = array();
                }
            }
            $groupCode .= $this->dumpRoutesGroupedByRegex($routes, $supportsRedirections);

            if (null !== $regex) {
                // apply extra indention at each line (except empty ones)
                $groupCode = preg_replace('/^.{2,}$/m', '    $0', $groupCode);
                $code .= $groupCode;
                $code .= "        }\n\n";
            } else {
                $code .= $groupCode;
            }
        }

        return $code;
    }

    private function dumpRoutesGroupedByRegex(array $routes, $supportsRedirections)
    {
        $code = '';
        $regexes = array();
        $routeMap = array();
        $suffixLen = 0;
        $suffix = '';

        $count = count($routes);
        /**
         * @var $route DumperRoute
         */
        foreach ($routes as $route) {
            $suffixLen++;
            $suffix .= "\t";
            $regex = $route->getRoute()->compile()->getRegex();
            preg_match('#\(\?P<([a-z_][a-z_0-9]{0,31})>#i', $regex, $vars);
            $regex = preg_replace('#\(\?P(<[a-z_][a-z_0-9]{0,31}>)#i', '(', $regex);
            $regex = str_replace('++', '+', $regex);

            $methods = $route->getRoute()->getMethods();
            // GET and HEAD are equivalent
            if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
                $methods[] = 'HEAD';
            }

            $supportsTrailingSlash = $supportsRedirections && (!$methods || in_array('HEAD', $methods));

            if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
            }

            $regex = substr($regex, 2, -3); // remove #^ and $#s

            $regexes[] = '' . $regex . '(\t{' . $suffixLen . '})\t{' . ($count - $suffixLen) . '}';


            $routeMap[$suffix] = ['route' => $route->getRoute(), 'vars' => $vars, 'name' => $route->getName()];
        }
        if (count($regexes) === 0) {
            return '';
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~s';

        $code .= 'if (preg_match(\''.$regex.'\', $pathinfo . \''.$suffix.'\', $rawMatch)) {'."\n";

        foreach ($routeMap as $suffix => $route) {
            if ($route['route']->getOption('priority')) {
                // Too late : a static route with lower priority have been found
                $code .= 'if ($maxPriority &&  '. $route['route']->getOption('priority') . ' > $maxPriority ) {
                    return null;
                }';
            }
            $code .= 'if ( end($rawMatch) === \'' . $suffix . '\') {';
            $code .= '    $matches = array();';
            $vars = count($route['vars']);
            for ($i = 1; $i < $vars; $i++) {
                $code .= '$matches[\'' . $route['vars'][$i] . '\'] = $rawMatch['.$i.'];' . "\n";

            }
            $code .= $this->compileRoute($route['route'], $route['name'], $supportsRedirections);

            $code .= '}';
        }

        $code .= '}'."\n";

        return $code;
    }

    /**
     * Compiles a single Route to PHP code used to match it against the path info.
     *
     * @param Route       $route                A Route instance
     * @param string      $name                 The name of the Route
     * @param bool        $supportsRedirections Whether redirections are supported by the base class
     * @param string|null $parentPrefix         The prefix of the parent collection used to optimize the code
     *
     * @return string PHP code
     *
     * @throws \LogicException
     */
    private function compileRoute(Route $route, $name, $supportsRedirections, $parentPrefix = null)
    {
        $code = '';
        $compiledRoute = $route->compile();
        $conditions = array('true');
        $hasTrailingSlash = false;
        $hostMatches = false;
        $methods = $route->getMethods();

        // GET and HEAD are equivalent
        if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        $supportsTrailingSlash = $supportsRedirections && (!$methods || in_array('HEAD', $methods));

        $regex = $compiledRoute->getRegex();
        if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
            $hasTrailingSlash = true;
        }

        if ($compiledRoute->getHostVariables()) {
            $hostMatches = true;
        }

        if ($route->getCondition()) {
            $conditions[] = $this->getExpressionLanguage()->compile(
                $route->getCondition(),
                array('context', 'request')
            );
        }

        $conditions = implode(' && ', $conditions);

        $code .= <<<EOF
        // $name
        if ($conditions) {

EOF;

        $gotoname = 'not_'.preg_replace('/[^A-Za-z0-9_]/', '', $name);
        if ($methods) {
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
                return array(
                    'priority' => {$route->getOption('priority')},
                    'options' => \$this->redirect(\$pathinfo.'/', '$name')
                );
            }

EOF;
        }

        if ($schemes = $route->getSchemes()) {
            if (!$supportsRedirections) {
                throw new \LogicException('The "schemes" requirement is only supported for URL matchers that implement RedirectableUrlMatcherInterface.');
            }
            $schemes = str_replace("\n", '', var_export(array_flip($schemes), true));
            $code .= <<<EOF
            \$requiredSchemes = $schemes;
            if (!isset(\$requiredSchemes[\$this->context->getScheme()])) {
                return array(
                    'priority' => {$route->getOption('priority')},
                    'options' => \$this->redirect(\$pathinfo, '$name', key(\$requiredSchemes))
                );
            }
EOF;
        }

        // optimize parameters array
        $vars = array('$matches');
        if ($hostMatches) {
            $vars[] = '$hostMatches';
        }
        $vars[] = "array('_route' => '$name')";

        $code .= sprintf(
            "            return array(
                            'priority' => {$route->getOption('priority')},
                            'options' => \$this->mergeDefaults(array_replace(%s), %s)
                         );\n",
            implode(', ', $vars),
            str_replace("\n", '', var_export($route->getDefaults(), true))
        );
        $code .= "        }\n";

        if ($methods) {
            $code .= "        $gotoname:\n";
        }

        return $code;
    }

    /**
     * Groups consecutive routes having the same host regex.
     *
     * The result is a collection of collections of routes having the same host regex.
     *
     * @param RouteCollection $routes A flat RouteCollection
     *
     * @return DumperCollection A collection with routes grouped by host regex in sub-collections
     */
    private function groupRoutesByHostRegex(RouteCollection $routes)
    {
        $groups = new DumperCollection();

        $currentGroup = new DumperCollection();
        $currentGroup->setAttribute('host_regex', null);
        $groups->add($currentGroup);

        /**
         * @var $route Route
         */
        foreach ($routes as $name => $route) {
            $hostRegex = $route->compile()->getHostRegex();
            if ($currentGroup->getAttribute('host_regex') !== $hostRegex) {
                $currentGroup = new DumperCollection();
                $currentGroup->setAttribute('host_regex', $hostRegex);
                $groups->add($currentGroup);
            }
            $currentGroup->add(new DumperRoute($name, $route));
        }

        return $groups;
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException(
                    'Unable to use expressions as the Symfony ExpressionLanguage component is not installed.'
                );
            }
            $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
        }

        return $this->expressionLanguage;
    }
}
