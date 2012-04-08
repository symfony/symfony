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
    if (ltrim('SessionHandlerInterface', '/') === $class) {
        require_once __DIR__.'/../Resources/stubs/SessionHandlerInterface.php';
    }

    if (0 !== strpos(ltrim($class, '/'), 'Symfony\Component\HttpFoundation')) {
        return;
    }

    require_once __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\HttpFoundation')).'.php';
});
