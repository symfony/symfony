<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Test;

/** @internal */
class NotUnserializable
{
    public function __unserialize(array $data): void
    {
        throw new \Exception(__CLASS__);
    }
}
