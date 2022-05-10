<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

/**
 * @author Marvin Feldmann <breyndot.echse@gmail.com>
 */
class JsonSerializableCircularReferenceDummy implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return [
            'me' => $this,
        ];
    }
}
