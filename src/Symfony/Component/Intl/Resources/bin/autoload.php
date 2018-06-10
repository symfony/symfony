<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$autoload = __DIR__.'/../../vendor/autoload.php';

if (!file_exists($autoload)) {
    bailout('You should run "composer install --dev" in the component before running this script.');
}

require_once $autoload;
