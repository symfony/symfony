<?php

namespace Symfony\Component\Routing\Matcher\Dumper;

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

            if ($compiledRoute->getStaticPrefix()) {
                $conditions[] = sprintf("0 === strpos(\$url, '%s')", $compiledRoute->getStaticPrefix());
            }

            $conditions[] = sprintf("preg_match('%s', \$url, \$matches)", $compiledRoute->getRegex());

            $conditions = implode(' && ', $conditions);

            $code[] = sprintf(<<<EOF
        if ($conditions) {
            return array_merge(\$this->mergeDefaults(\$matches, %s), array('_route' => '%s'));
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
