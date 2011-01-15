<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\Routing\Route;

/**
 * PhpMatcherDumper creates a PHP class able to match URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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

    protected function addMatcher()
    {
        $code = array();

        foreach ($this->routes->all() as $name => $route) {
            $compiledRoute = $route->compile();

            $conditions = array();

            if ($req = $route->getRequirement('_method')) {
                $conditions[] = sprintf("isset(\$this->context['method']) && preg_match('#^(%s)$#xi', \$this->context['method'])", $req);
            }

            if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', $compiledRoute->getRegex(), $m)) {
                $conditions[] = sprintf("\$url === '%s'", str_replace('\\', '', $m['url']));

                $matches = 'array()';
            } else {
                if ($compiledRoute->getStaticPrefix()) {
                    $conditions[] = sprintf("0 === strpos(\$url, '%s')", $compiledRoute->getStaticPrefix());
                }

                $conditions[] = sprintf("preg_match('%s', \$url, \$matches)", $compiledRoute->getRegex());

                $matches = '$matches';
            }

            $conditions = implode(' && ', $conditions);

            $code[] = sprintf(<<<EOF
        if ($conditions) {
            return array_merge(\$this->mergeDefaults($matches, %s), array('_route' => '%s'));
        }

EOF
            , str_replace("\n", '', var_export($compiledRoute->getDefaults(), true)), $name);
        }

        $code = implode("\n", $code);

        return <<<EOF

    public function match(\$url)
    {
        \$url = \$this->normalizeUrl(\$url);

$code
        return false;
    }

EOF;
    }

    protected function startClass($class, $baseClass)
    {
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
