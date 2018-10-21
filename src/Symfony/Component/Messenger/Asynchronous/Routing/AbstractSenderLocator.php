<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Routing;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @internal
 */
abstract class AbstractSenderLocator implements SenderLocatorInterface
{
    public static function getValueFromMessageRouting(array $mapping, Envelope $envelope)
    {
        if (isset($mapping[$class = \get_class($envelope->getMessage())])) {
            return $mapping[$class];
        }

        foreach (class_parents($class) as $name) {
            if (isset($mapping[$name])) {
                return $mapping[$name];
            }
        }

        foreach (class_implements($class) as $name) {
            if (isset($mapping[$name])) {
                return $mapping[$name];
            }
        }

        return $mapping['*'] ?? null;
    }
}
