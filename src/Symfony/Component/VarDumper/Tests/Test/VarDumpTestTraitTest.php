<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Skipping trait tests for PHP < 5.4
if (version_compare(PHP_VERSION, '5.4.0-dev', '>=')) {
    require 'VarDumpTestTraitRequire54.php';
}

