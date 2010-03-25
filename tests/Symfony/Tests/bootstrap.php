<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../src/Symfony/Foundation/UniversalClassLoader.php';
require_once 'PHPUnit/Framework.php';

$loader = new Symfony\Foundation\UniversalClassLoader();
$loader->registerNamespace('Symfony', __DIR__.'/../../../src');
$loader->register();
