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
        'SYMFONY_HTTP_FOUNDATION'      => 'HttpFoundation',
        'SYMFONY_DEPENDENCY_INJECTION' => 'DependencyInjection',
        'SYMFONY_FORM'                 => 'Form',
        'SYMFONY_SECURITY'             => 'Security',
        'SYMFONY_VALIDATOR'            => 'Validator',
        'SYMFONY_HTTP_KERNEL'          => 'HttpKernel',
        'SYMFONY_EVENT_DISPATCHER'     => 'EventDispatcher',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (isset($_SERVER['SYMFONY_HTTP_FOUNDATION']) && 'SessionHandlerInterface' === ltrim($class, '/')) {
        require_once $_SERVER['SYMFONY_HTTP_FOUNDATION'].'/Resources/stubs/SessionHandlerInterface.php';
    }

    if (isset($_SERVER['DOCTRINE_FIXTURES']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\Common\DataFixtures')) {
        if (file_exists($file = $_SERVER['DOCTRINE_FIXTURES'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (isset($_SERVER['DOCTRINE_COMMON']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\Common')) {
        if (file_exists($file = $_SERVER['DOCTRINE_COMMON'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (isset($_SERVER['DOCTRINE_DBAL']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\DBAL')) {
        if (file_exists($file = $_SERVER['DOCTRINE_DBAL'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (isset($_SERVER['DOCTRINE_ORM']) && 0 === strpos(ltrim($class, '/'), 'Doctrine\ORM')) {
        if (file_exists($file = $_SERVER['DOCTRINE_ORM'].'/lib/'.str_replace('\\', '/', $class).'.php')) {
            require_once $file;
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Bridge\Doctrine')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Bridge\Doctrine')).'.php')) {
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
