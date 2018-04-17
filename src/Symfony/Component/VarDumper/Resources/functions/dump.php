<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function dump($var, ...$moreVars) {
        VarDumper::dump($var);

        foreach ($moreVars as $var) {
            VarDumper::dump($var);
        }

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dump_die')) {
    /**
     * @author Joubert RedRat <me+symfony@redrat.com.br>
     */
    function dump_die($var, ...$moreVars) {
        dump($var, $moreVars);
        die;
    }
}
