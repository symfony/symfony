<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../ExecutableFinder.php';

use Symfony\Component\Process\ExecutableFinder;

putenv('PATH='.dirname(PHP_BINARY));

function getPhpBinaryName(): string
{
    return basename(PHP_BINARY, '\\' === DIRECTORY_SEPARATOR ? '.exe' : '');
}

echo (new ExecutableFinder())->find(getPhpBinaryName());
