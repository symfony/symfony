<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\VarDumper\Dumper\VarDumperOptions;
use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     * @author Alexandre Daubois <alex.daubois@gmail.com>
     *
     * @template T
     *
     * @param T ...$vars
     *
     * @return T|VarDumperOptions
     */
    function dump(mixed ...$vars): mixed
    {
        $options = new VarDumperOptions($vars);

        if (!$vars) {
            return $options;
        }

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            VarDumper::dump($vars[0], null, $options);
            $k = 0;
        } else {
            $vars = array_filter($vars, static fn (int|string $key) => !str_starts_with($key, '_'), \ARRAY_FILTER_USE_KEY);
            foreach ($vars as $k => $v) {
                VarDumper::dump($v, is_int($k) ? 1 + $k : $k, $options);
            }
        }

        if (1 < count($vars)) {
            return $vars;
        }

        return $vars[$k];
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        if (!in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        $options = new VarDumperOptions($vars);

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            VarDumper::dump($vars[0], null, $options);
        } else {
            foreach ($vars as $k => $v) {
                if (str_starts_with($k, '_')) {
                    continue;
                }

                VarDumper::dump($v, is_int($k) ? 1 + $k : $k, $options);
            }
        }

        exit(1);
    }
}
