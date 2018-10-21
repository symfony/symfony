<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender\Locator;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @internal
 */
abstract class AbstractSenderLocator implements SenderLocatorInterface
{
    public static function getValueFromMessageRouting(array $mapping, string $topic)
    {
        if (isset($mapping[$topic])) {
            return $mapping[$topic];
        }

        if (!class_exists($topic) && !interface_exists($topic, false)) {
            return $mapping['*'] ?? null;
        }

        foreach (class_parents($topic) as $name) {
            if (isset($mapping[$name])) {
                return $mapping[$name];
            }
        }

        foreach (class_implements($topic) as $name) {
            if (isset($mapping[$name])) {
                return $mapping[$name];
            }
        }

        return $mapping['*'] ?? null;
    }
}
