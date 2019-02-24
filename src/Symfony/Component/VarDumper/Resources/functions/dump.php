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
    function dump($var, ...$moreVars)
    {
        VarDumper::dump($var);

        foreach ($moreVars as $v) {
            VarDumper::dump($v);
        }

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dumpif')) {
    /**
     * @author Tales Santos <tales.augusto.santos@gmail.com>
     */
    function dumpif($var, ...$moreVars)
    {
        $condition = array_pop($moreVars);

        if (!is_callable($condition)) {
            throw new \InvalidArgumentException('You should provide a condition in order to dump $var');
        }

        if (true !== call_user_func($condition)) {
            return $var;
        }

        return dump($var, $moreVars);
    }
}

if (!function_exists('dd')) {
    function dd(...$vars)
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }

        die(1);
    }
}
