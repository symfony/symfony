<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Watcher\Resource\Locator;

use Symfony\Component\Filesystem\Watcher\Resource\ResourceInterface;

interface LocatorInterface
{
    public function locate($path): ?ResourceInterface;
}
