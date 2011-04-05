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
        $supportsTrailingSlash = isset($interfaces['Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface']);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addMatcher($supportsTrailingSlash).
            $this->endClass()
        ;
    }

    private function addMatcher($supportsTrailingSlash)
    {
        $code = array();

        foreach ($this->getRoutes()->all() as $name => $route) {
            $compiledRoute = $route->compile();
            $conditions = array();
            $hasTrailingSlash = false;
            $matches = false;
            if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', $compiledRoute->getRegex(), $m)) {
                if ($supportsTrailingSlash && substr($m['url'], -1) === '/') {
                    $conditions[] = sprintf("rtrim(\$pathinfo, '/') === '%s'", rtrim(str_replace('\\', '', $m['url']), '/'));
                    $hasTrailingSlash = true;
                } else {
                    $conditions[] = sprintf("\$pathinfo === '%s'", str_replace('\\', '', $m['url']));
                }
            } else {
                if ($compiledRoute->getStaticPrefix()) {
                    $conditions[] = sprintf("0 === strpos(\$pathinfo, '%s')", $compiledRoute->getStaticPrefix());
                }

                $regex = $compiledRoute->getRegex();
                if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
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
                $req = implode('\', \'', array_map('strtolower', explode('|', $req)));
                $code[] = <<<EOF
            if (isset(\$this->context['method']) && !in_array(strtolower(\$this->context['method']), array('$req'))) {
                \$allow = array_merge(\$allow, array('$req'));
                goto $gotoname;
            }
EOF;
            }

            if ($hasTrailingSlash) {
                $code[] = sprintf(<<<EOF
            if (substr(\$pathinfo, -1) !== '/') {
                return \$this->redirect(\$pathinfo.'/', '%s');
            }
EOF
                , $name);
            }

            // optimize parameters array
            if (true === $matches && $compiledRoute->getDefaults()) {
                $code[] = sprintf("            return array_merge(\$this->mergeDefaults(\$matches, %s), array('_route' => '%s'));"
                    , str_replace("\n", '', var_export($compiledRoute->getDefaults(), true)), $name);
            } elseif (true === $matches) {
                $code[] = sprintf("            \$matches['_route'] = '%s';\n            return \$matches;", $name);
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
        }

        $code = implode("\n", $code);

        return <<<EOF

    public function match(\$pathinfo)
    {
        \$allow = array();

$code
        throw 0 < count(\$allow) ? new MethodNotAllowedException(array_unique(\$allow)) : new NotFoundException();
    }

EOF;
    }

    private function startClass($class, $baseClass)
    {
        return <<<EOF
<?php

use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;

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
    public function __construct(array \$context = array(), array \$defaults = array())
    {
        \$this->context = \$context;
        \$this->defaults = \$defaults;
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
