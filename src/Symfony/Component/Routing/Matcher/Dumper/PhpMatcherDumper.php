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
     * @param  array  $options An array of options
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
        $supportsRedirections = isset($interfaces['Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface']);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addMatcher($supportsRedirections).
            $this->endClass()
        ;
    }

    private function addMatcher($supportsRedirections)
    {
        $code = implode("\n", $this->compileRoutes($this->getRoutes(), $supportsRedirections));

        return <<<EOF

    public function match(\$pathinfo)
    {
        \$allow = array();

$code
        throw 0 < count(\$allow) ? new MethodNotAllowedException(array_unique(\$allow)) : new NotFoundException();
    }

EOF;
    }

    private function compileRoutes(RouteCollection $routes, $supportsRedirections)
    {
        $code = array();
        foreach ($routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                $indent = '';
                if (count($route->all()) > 1 && $prefix = $route->getPrefix()) {
                    $code[] = sprintf("        if (0 === strpos(\$pathinfo, '%s')) {", $prefix);
                    $indent = '    ';
                }

                foreach ($this->compileRoutes($route, $supportsRedirections) as $line) {
                    foreach (explode("\n", $line) as $l) {
                        $code[] = $indent.$l;
                    }
                }

                if ($indent) {
                    $code[] = "        }\n";
                }
            } else {
                foreach ($this->compileRoute($route, $name, $supportsRedirections) as $line) {
                    $code[] = $line;
                }
            }
        }

        return $code;
    }

    private function compileRoute(Route $route, $name, $supportsRedirections)
    {
        $compiledRoute = $route->compile();
        $conditions = array();
        $hasTrailingSlash = false;
        $matches = false;
        if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', str_replace(array("\n", ' '), '', $compiledRoute->getRegex()), $m)) {
            if ($supportsRedirections && substr($m['url'], -1) === '/') {
                $conditions[] = sprintf("rtrim(\$pathinfo, '/') === '%s'", rtrim(str_replace('\\', '', $m['url']), '/'));
                $hasTrailingSlash = true;
            } else {
                $conditions[] = sprintf("\$pathinfo === '%s'", str_replace('\\', '', $m['url']));
            }
        } else {
            if ($compiledRoute->getStaticPrefix()) {
                $conditions[] = sprintf("0 === strpos(\$pathinfo, '%s')", $compiledRoute->getStaticPrefix());
            }

            $regex = str_replace(array("\n", ' '), '', $compiledRoute->getRegex());
            if ($supportsRedirections && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
                $hasTrailingSlash = true;
            }
            $conditions[] = sprintf("preg_match('%s', \$pathinfo, \$matches)", $regex);

            $matches = true;
        }

        $conditions = implode(' && ', $conditions);

        $gotoname = 'not_'.preg_replace('/[^A-Za-z0-9_]/', '', $name);

        $code[] = <<<EOF
        // $name
        if ($conditions) {
EOF;

        if ($req = $route->getRequirement('_method')) {
            $methods = array_map('strtolower', explode('|', $req));
            if (1 === count($methods)) {
                $code[] = <<<EOF
            if (\$this->context->getMethod() != '$methods[0]') {
                \$allow[] = '$methods[0]';
                goto $gotoname;
            }
EOF;
            } else {
                $methods = implode('\', \'', $methods);
                $code[] = <<<EOF
            if (!in_array(\$this->context->getMethod(), array('$methods'))) {
                \$allow = array_merge(\$allow, array('$methods'));
                goto $gotoname;
            }
EOF;
            }
        }

        if ($hasTrailingSlash) {
            $code[] = sprintf(<<<EOF
            if (substr(\$pathinfo, -1) !== '/') {
                return \$this->redirect(\$pathinfo.'/', '%s');
            }
EOF
            , $name);
        }

        if ($scheme = $route->getRequirement('_scheme')) {
            if (!$supportsRedirections) {
                throw new \LogicException('The "_scheme" requirement is only supported for route dumper that implements RedirectableUrlMatcherInterface.');
            }

            $code[] = sprintf(<<<EOF
            if (\$this->context->getScheme() !== '$scheme') {
                return \$this->redirect(\$pathinfo, '%s', '$scheme');
            }
EOF
            , $name);
        }

        // optimize parameters array
        if (true === $matches && $compiledRoute->getDefaults()) {
            $code[] = sprintf("            return array_merge(\$this->mergeDefaults(\$matches, %s), array('_route' => '%s'));"
                , str_replace("\n", '', var_export($compiledRoute->getDefaults(), true)), $name);
        } elseif (true === $matches) {
            $code[] = sprintf("            \$matches['_route'] = '%s';", $name);
            $code[] = sprintf("            return \$matches;", $name);
        } elseif ($compiledRoute->getDefaults()) {
            $code[] = sprintf('            return %s;', str_replace("\n", '', var_export(array_merge($compiledRoute->getDefaults(), array('_route' => $name)), true)));
        } else {
            $code[] = sprintf("            return array('_route' => '%s');", $name);
        }
        $code[] = "        }";

        if ($req) {
            $code[] = "        $gotoname:";
        }

        $code[] = '';

        return $code;
    }

    private function startClass($class, $baseClass)
    {
        return <<<EOF
<?php

use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class $class extends $baseClass
{

EOF;
    }

    private function addConstructor()
    {
        return <<<EOF
    /**
     * Constructor.
     */
    public function __construct(RequestContext \$context)
    {
        \$this->context = \$context;
    }

EOF;
    }

    private function endClass()
    {
        return <<<EOF
}

EOF;
    }
}
