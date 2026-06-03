<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace acme\lib;

class PhpDeprecation implements \Serializable
{
    public function serialize(): string
    {
        return serialize([]);
    }

    public function unserialize($data): void
    {
    }
}
