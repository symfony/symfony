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
        'php composer.phar install --dev'.PHP_EOL);
}
