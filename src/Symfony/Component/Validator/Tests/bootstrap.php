<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!$loader = include __DIR__.'/../vendor/autoload.php') {
    echo PHP_EOL.PHP_EOL;
    die('You must set up the project dependencies.'.PHP_EOL.
        'Run the following commands in '.dirname(__DIR__).':'.PHP_EOL.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}

Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(function($class) {
    if (0 === strpos(ltrim($class, '/'), 'Symfony\Component\Validator')) {
        if (file_exists($file = __DIR__.'/../'.substr(str_replace('\\', '/', $class), strlen('Symfony\Component\Validator')).'.php')) {
            require_once $file;
        }
    }

    return class_exists($class, false);
});
