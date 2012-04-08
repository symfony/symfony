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
        'SYMFONY_EVENT_DISPATCHER'     => 'EventDispatcher',
        'SYMFONY_HTTP_FOUNDATION'      => 'HttpFoundation',
        'SYMFONY_DEPENDENCY_INJECTION' => 'DependencyInjection',
        'SYMFONY_CONSOLE'              => 'Console',
        'SYMFONY_BROWSER_KIT'          => 'BrowserKit',
        'SYMFONY_FINDER'               => 'Finder',
        'SYMFONY_CLASS_LOADER'         => 'ClassLoader',
        'SYMFONY_PROCESS'              => 'Process',
        'SYMFONY_ROUTING'              => 'Routing',
        'SYMFONY_CONFIG'               => 'Config',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\HttpKernel')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\HttpKernel')).'.php')) {
            require_once $file;
        }
    }
});
