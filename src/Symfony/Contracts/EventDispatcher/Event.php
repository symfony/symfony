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

use Psr\EventDispatcher\StoppableEventInterface;

if (interface_exists(StoppableEventInterface::class)) {
    require __DIR__.\DIRECTORY_SEPARATOR.'Event+psr14.php';
} else {
    require __DIR__.\DIRECTORY_SEPARATOR.'Event-psr14.php';
}
