<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

class JsonSerializableDummy implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return array(
            'foo' => 'a',
            'bar' => 'b',
            'baz' => 'c',
            'qux' => $this,
        );
    }
}
