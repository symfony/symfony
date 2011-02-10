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
 * ApacheMatcherDumper dumps a matcher in the Apache .htaccess format.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ApacheMatcherDumper extends MatcherDumper
{
    /**
     * Dumps a set of routes to a .htaccess format.
     *
     * Available options:
     *
     *  * script_name: The script name (app.php by default)
     *
     * @param  array  $options An array of options
     *
     * @return string A string to be used as Apache rewrite rules.
     *
     * @throws \RuntimeException When a route has more than 9 variables
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'script_name' => 'app.php',
        ), $options);

        $regexes = array();

        foreach ($this->routes->all() as $name => $route) {
            $compiledRoute = $route->compile();

            // Apache "only" supports 9 variables
            if (count($compiledRoute->getVariables()) > 9) {
                throw new \RuntimeException(sprintf('Unable to dump a route collection as route "%s" has more than 9 variables', $name));
            }

            $regex = preg_replace('/\?P<.+?>/', '', substr($compiledRoute->getRegex(), 1, -2));

            $variables = array('E=_ROUTING__route:'.$name);
            foreach (array_keys($compiledRoute->getVariables()) as $i => $variable) {
                $variables[] = 'E=_ROUTING_'.$variable.':%'.($i + 1);
            }
            foreach ($route->getDefaults() as $key => $value) {
                $variables[] = 'E=_ROUTING_'.$key.':'.$value;
            }
            $variables = implode(',', $variables);

            $conditions = array();
            if ($req = $route->getRequirement('_method')) {
                $conditions[] = sprintf('RewriteCond %%{REQUEST_METHOD} ^(%s) [NC]', $req);
            }

            $conditions = count($conditions) ? implode(" [OR]\n", $conditions)."\n" : '';

            $regexes[] = sprintf("%sRewriteCond %%{PATH_INFO} %s\nRewriteRule .* %s [QSA,L,%s]", $conditions, $regex, $options['script_name'], $variables);

            // add redirect for missing trailing slash
            if ('/$' === substr($regex, -2) && '^/$' !== $regex) {
                $regexes[count($regexes)-1] .= sprintf("\nRewriteCond %%{PATH_INFO} %s\nRewriteRule .* /$0/ [QSA,L,R=301]", substr($regex, 0, -2).'$');
            }
        }

        return implode("\n\n", $regexes);
    }
}
