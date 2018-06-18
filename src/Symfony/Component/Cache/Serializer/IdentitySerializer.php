<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Serializer;

use Symfony\Component\Cache\SerializerInterface;

class IdentitySerializer implements SerializerInterface
{
    public function serialize($data)
    {
        return $data;
    }

    public function unserialize($serialized)
    {
        return $serialized;
    }
}
