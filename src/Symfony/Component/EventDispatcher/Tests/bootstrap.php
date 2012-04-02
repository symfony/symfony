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
        'SYMFONY_DEPENDENCY_INJECTION' => 'DependencyInjection',
        'SYMFONY_HTTP_KERNEL'          => 'HttpKernel',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\EventDispatcher')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\EventDispatcher')).'.php')) {
            require_once $file;
        }
    }
});
