<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\FilesystemTrait;

class FilesystemCache extends AbstractCache implements PruneableInterface
{
    use FilesystemTrait;

    public function __construct(string $namespace = '', int $defaultLifetime = 0, string $directory = null)
    {
        parent::__construct('', $defaultLifetime);
        $this->init($namespace, $directory);
    }
}
