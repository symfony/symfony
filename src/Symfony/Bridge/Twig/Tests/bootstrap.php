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
        'SYMFONY_EVENT_DISPATCHER' => 'EventDispatcher',
        'SYMFONY_FORM'             => 'Form',
        'SYMFONY_LOCALE'           => 'Locale',
        'SYMFONY_TEMPLATING'       => 'Templating',
        'SYMFONY_TRANSLATION'      => 'Translation',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (isset($_SERVER['TWIG']) && 0 === strpos(ltrim($class, '/'), 'Twig_')) {
        if (file_exists($file = $_SERVER['TWIG'].'/lib/'.str_replace('_', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Bridge\Twig')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Bridge\Twig')).'.php')) {
            require_once $file;
        }
    }
});
