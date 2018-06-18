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

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\SerializerInterface;

class IgbinarySerializer implements SerializerInterface
{
    public function serialize($data)
    {
        return igbinary_serialize($data);
    }

    public function unserialize($serialized)
    {
        $unserializeCallbackHandler = ini_set(
            'unserialize_callback_func',
            PhpSerializer::class.'::handleUnserializeCallback'
        );
        try {
            $value = igbinary_unserialize($serialized);
            if (false === $value && igbinary_serialize(false) !== $serialized) {
                throw new CacheException('failed to unserialize value');
            }

            return $value;
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }
}
