<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Symfony\\Component\\HtmlRenderer\\';
    if (str_starts_with($class, $prefix) && is_file($file = dirname(__DIR__).'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
        require $file;
    }
});
