<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;

require __DIR__.'/../../Exception/ExceptionInterface.php';
require __DIR__.'/../../Exception/IOExceptionInterface.php';
require __DIR__.'/../../Exception/IOException.php';
require __DIR__.'/../../Filesystem.php';

if (\function_exists('mkdir')) {
    throw new \LogicException('The mkdir function must be disabled.');
}

(new Filesystem())->remove($argv[1]);
