<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp used to override the transport names specified in the Messenger routing configuration file.
 */
final class TransportNamesStamp implements StampInterface
{
    private array $transportNames;

    /**
     * @param string[]|string $transportNames Transport names to be used for the message
     */
    public function __construct(array|string $transportNames)
    {
        $this->transportNames = (array) $transportNames;
    }

    public function getTransportNames(): array
    {
        return $this->transportNames;
    }
}
