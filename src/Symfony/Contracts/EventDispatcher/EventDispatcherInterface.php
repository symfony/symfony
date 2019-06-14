<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

if (interface_exists(PsrEventDispatcherInterface::class)) {
    require __DIR__.\DIRECTORY_SEPARATOR.'EventDispatcherInterface+psr14.php';
} else {
    require __DIR__.\DIRECTORY_SEPARATOR.'EventDispatcherInterface-psr14.php';
}
