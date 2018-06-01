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

class PhpSerializer implements SerializerInterface
{
    public function serialize($data)
    {
        return serialize($data);
    }

    public function unserialize($serialized)
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        try {
            if ('b:0;' === $serialized) {
                return false;
            } elseif (false === $value = unserialize($serialized)) {
                throw new CacheException('failed to unserialize value');
            }

            return $value;
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback($class)
    {
        throw new \DomainException('Class not found: '.$class);
    }
}
