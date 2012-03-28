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
        'SYMFONY_HTTP_KERNEL' => 'HttpKernel',
        'SYMFONY_EVENT_DISPATCHER' => 'EventDispatcher',
        'SYMFONY_FORM' => 'Form',
        'SYMFONY_ROUTING' => 'Routing',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (isset($_SERVER['DOCTRINE_DBAL']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\DBAL')) {
        if (file_exists($file = $_SERVER['DOCTRINE_DBAL'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (isset($_SERVER['DOCTRINE_COMMON']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\Common')) {
        if (file_exists($file = $_SERVER['DOCTRINE_COMMON'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\Security')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\Security')).'.php')) {
            require_once $file;
        }
    }
});
