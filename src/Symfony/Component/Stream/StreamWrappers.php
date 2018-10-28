<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stream;

/**
 * Native PHP stream wrapper manager.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class StreamWrappers
{
    /**
     * @return string[]
     */
    public static function getProtocols(): array
    {
        return stream_get_wrappers();
    }

    public static function hasProtocol(string $name): bool
    {
        return \in_array($name, stream_get_wrappers(), true);
    }

    public static function register(Protocol $protocol): void
    {
        if (self::hasProtocol($name = $protocol->getName())) {
            throw new \LogicException(sprintf('The stream protocol "%s" is already registered.', $name));
        }
        if (!stream_wrapper_register($name, $class = $protocol->getWrapperClass(), $protocol->isRemote() ? STREAM_IS_URL : 0)) {
            throw new \RuntimeException(sprintf('Unable to register the stream wrapper "%s" for protocol "%s".', $class, $name));
        }
    }

    public static function unregister(Protocol $protocol): void
    {
        if (!self::hasProtocol($name = $protocol->getName())) {
            return;
        }
        if (!stream_wrapper_unregister($name)) {
            throw new \RuntimeException(sprintf('Unable to unregister the stream wrapper for protocol "%s".', $name));
        }
    }
}
