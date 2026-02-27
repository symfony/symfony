<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$phar = new Phar(__DIR__.\DIRECTORY_SEPARATOR.'deprecation.phar', 0, 'deprecation.phar');
$phar->buildFromDirectory(__DIR__.\DIRECTORY_SEPARATOR.'deprecation');
