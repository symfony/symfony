<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

spl_autoload_register(function ($class) {
    foreach (array(
        'SYMFONY_HTTP_FOUNDATION' => 'HttpFoundation',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (isset($_SERVER['MONOLOG']) && 0 === strpos(ltrim($class, '/'), 'Monolog')) {
        if (file_exists($file = $_SERVER['MONOLOG'].'/src/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Bridge\Monolog')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Bridge\Monolog')).'.php')) {
            require_once $file;
        }
    }
});
