<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Exception;

use Psr\SimpleCache\LogicException as SimpleCacheInterface;

if (interface_exists(SimpleCacheInterface::class)) {
    require __DIR__.\DIRECTORY_SEPARATOR.'LogicException+psr16.php';
} else {
    require __DIR__.\DIRECTORY_SEPARATOR.'LogicException-psr16.php';
}
