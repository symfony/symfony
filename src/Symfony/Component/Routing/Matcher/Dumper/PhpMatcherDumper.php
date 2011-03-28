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

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addMatcher().
            $this->endClass()
        ;
    }

    private function addMatcher()
    {
        $code = array();

        foreach ($this->getRoutes()->all() as $name => $route) {
            $compiledRoute = $route->compile();

            $conditions = array();

            $hasTrailingSlash = false;
            if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', $compiledRoute->getRegex(), $m)) {
                if (substr($m['url'], -1) === '/') {
                    $conditions[] = sprintf("rtrim(\$pathinfo, '/') === '%s'", rtrim(str_replace('\\', '', $m['url']), '/'));
                    $hasTrailingSlash = true;
                } else {
                    $conditions[] = sprintf("\$pathinfo === '%s'", str_replace('\\', '', $m['url']));
                }

                $matches = 'array()';
            } else {
                if ($compiledRoute->getStaticPrefix()) {
                    $conditions[] = sprintf("0 === strpos(\$pathinfo, '%s')", $compiledRoute->getStaticPrefix());
                }

                $regex = $compiledRoute->getRegex();
                if ($pos = strpos($regex, '/$')) {
                    $regex = substr($regex, 0, $pos) . '/?$' . substr($regex, $pos+2);
                    $conditions[] = sprintf("preg_match('%s', \$pathinfo, \$matches)", $regex);
                    $hasTrailingSlash = true;
                } else {
                    $conditions[] = sprintf("preg_match('%s', \$pathinfo, \$matches)", $regex);
                }

                $matches = '$matches';
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
                return array('_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction', 'url' => \$this->context['base_url'].\$pathinfo.'/', 'permanent' => true, '_route' => '%s');
            }
EOF
            , $name);
            }

            $code[] = sprintf(<<<EOF
            return array_merge(\$this->mergeDefaults($matches, %s), array('_route' => '%s'));
        }
EOF
            , str_replace("\n", '', var_export($compiledRoute->getDefaults(), true)), $name);

            if ($req) {
                $code[] = <<<EOF
        $gotoname:
EOF;
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
