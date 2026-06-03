<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * Helps reduce the size of the dumped container when using php-serialize.
 *
 * @internal
 */
trait ArgumentTrait
{
    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (null === $v) {
                continue;
            }
            if (false !== $i = strrpos($k, "\0")) {
                $k = substr($k, 1 + $i);
            }
            $data[$k] = $v;
        }

        return $data;
    }
}
