<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// We have not caught up on the deprecations yet and still call the other lib
// in a deprecated way.

include __DIR__.'/../lib/SomeService.php';
$defraculator = new \acme\lib\SomeService();
$defraculator->deprecatedApi();
