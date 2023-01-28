<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Attribute;

/**
 * Attribute to configure transports to be used to dispatch a message.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsRoutedMessage
{
    private array $transports;

    public function __construct(array|string $transports)
    {
        $this->transports = (array) $transports;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }
}
