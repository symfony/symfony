<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller\PhpMarshaller;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Values
{
    public function __construct(array $values)
    {
        foreach ($values as $i => $v) {
            $this->$i = $v;
        }
    }

    public static function __set_state($values)
    {
        foreach ($values as $i => $v) {
            Registry::$references[$i] = $v;
        }
    }
}
