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
        'SYMFONY_HTTP_KERNEL'     => 'HttpKernel',
        'SYMFONY_FORM'            => 'Form',
    ) as $env => $name) {
        if (isset($_SERVER[$env]) && 0 === strpos(ltrim($class, '/'), 'Symfony\Component\\'.$name)) {
            if (file_exists($file = $_SERVER[$env].'/'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\\'.$name)).'.php')) {
                require_once $file;
            }
        }
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Bridge\Propel1')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Bridge\Propel1')).'.php')) {
            require_once $file;
        }
    }
});

if (isset($_SERVER['PROPEL1']) && is_file($file = $_SERVER['PROPEL1'].'/runtime/lib/Propel.php')) {
    require_once $file;
}
