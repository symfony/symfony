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
 * Dumps a set of Apache mod_rewrite rules.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class ApacheMatcherDumper extends MatcherDumper
{
    /**
     * Dumps a set of Apache mod_rewrite rules.
     *
     * Available options:
     *
     *  * script_name: The script name (app.php by default)
     *  * base_uri:    The base URI ("" by default)
     *
     * @param array $options An array of options
     *
     * @return string A string to be used as Apache rewrite rules
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'script_name' => 'app.php',
            'base_uri'    => '',
        ), $options);

        $rules = array("# skip \"real\" requests\nRewriteCond %{REQUEST_FILENAME} -f\nRewriteRule .* - [QSA,L]");
        $methodVars = array();

        foreach ($this->getRoutes()->all() as $name => $route) {
            $compiledRoute = $route->compile();

            // prepare the apache regex
            $regex = preg_replace('/\?P<.+?>/', '', substr(str_replace(array("\n", ' '), '', $compiledRoute->getRegex()), 1, -2));
            $regex = '^'.preg_quote($options['base_uri']).substr($regex, 1);

            $hasTrailingSlash = '/$' == substr($regex, -2) && '^/$' != $regex;

            $variables = array('E=_ROUTING__route:'.$name);
            foreach ($compiledRoute->getVariables() as $i => $variable) {
                $variables[] = 'E=_ROUTING_'.$variable.':%'.($i + 1);
            }
            foreach ($route->getDefaults() as $key => $value) {
                // todo: a more legit way to escape the value?
                $variables[] = 'E=_ROUTING_'.$key.':'.strtr($value, array(
                    ':'  => '\\:',
                    '='  => '\\=',
                    '\\' => '\\\\',
                ));
            }
            $variables = implode(',', $variables);

            $rule = array("# $name");

            // method mismatch
            if ($req = strtolower($route->getRequirement('_method'))) {
                $allow = array();
                foreach (explode('|', $req) as $method) {
                    $methodVars[] = $var = '_ROUTING__allow_'.$method;
                    $allow[] = 'E='.$var.':1';
                }

                $rule[] = "RewriteCond %{REQUEST_URI} $regex";
                $rule[] = "RewriteCond %{REQUEST_METHOD} !^($req)$ [NC]";
                $rule[] = sprintf('RewriteRule .* - [S=%d,%s]', $hasTrailingSlash ? 2 : 1, implode(',', $allow));
            }

            // redirect with trailing slash appended
            if ($hasTrailingSlash) {
                $rule[] = 'RewriteCond %{REQUEST_URI} '.substr($regex, 0, -2).'$';
                $rule[] = 'RewriteRule .* $0/ [QSA,L,R=301]';
            }

            // the main rule
            $rule[] = "RewriteCond %{REQUEST_URI} $regex";
            $rule[] = "RewriteRule .* {$options['script_name']} [QSA,L,$variables]";

            $rules[] = implode("\n", $rule);
        }

        if (0 < count($methodVars)) {
            $rule = array('# 405 Method Not Allowed');
            foreach ($methodVars as $i => $methodVar) {
                $rule[] = sprintf('RewriteCond %%{%s} !-z%s', $methodVar, isset($methodVars[$i + 1]) ? ' [OR]' : '');
            }
            $rule[] = sprintf('RewriteRule .* %s [QSA,L]', $options['script_name']);

            $rules[] = implode("\n", $rule);
        }

        return implode("\n\n", $rules)."\n";
    }
}
