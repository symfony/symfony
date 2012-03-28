<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

spl_autoload_register($loader = function ($class) {
    foreach (array(
        'SYMFONY_YAML'            => 'Yaml',
        'SYMFONY_LOCALE'          => 'Locale',
        'SYMFONY_HTTP_FOUNDATION' => 'HttpFoundation',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (isset($_SERVER['DOCTRINE_COMMON']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\Common')) {
        if (file_exists($file = $_SERVER['DOCTRINE_COMMON'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\Validator')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\Validator')).'.php')) {
            require_once $file;
        }
    }
});

if (isset($_SERVER['DOCTRINE_COMMON'])) {
    Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(function($class) use ($loader) {
        $loader($class);

        return class_exists($class, false);
    });
}
