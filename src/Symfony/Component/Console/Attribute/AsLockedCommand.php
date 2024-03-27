<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsLockedCommand
{
    /**
     * @param string|bool|array $lock     The string used as the key to create lock, or a boolean to use the command's name, or callable which returns a string
     * @param bool              $blocking Is this lock should be blocking ? true to wait until the lock is available, false otherwise
     */
    public function __construct(
        public string|bool|array $lock = true,
        public bool $blocking = false,
    ) {
    }
}
