<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('intl_get_error_code')) {
    require_once __DIR__.'/../Resources/stubs/functions.php';
}

spl_autoload_register(function ($class) {
    if (in_array(ltrim($class, '/'), array('Collator', 'IntlDateFormatter', 'Locale', 'NumberFormatter'))) {
        require_once __DIR__.'/../Resources/stubs/'.ltrim($class, '/').'.php';
    }

    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\Locale')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\Locale')).'.php')) {
            require_once $file;
        }
    }
});
