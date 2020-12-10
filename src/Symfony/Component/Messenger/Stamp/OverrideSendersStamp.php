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
 * Apply this stamp to change sender to be used instead of the one from configuration file.
 */
final class OverrideSendersStamp implements StampInterface
{
    private $senders;

    /**
     * @param array $senders New senders to be used with message
     */
    public function __construct(array $senders)
    {
        $this->senders = $senders;
    }

    public function getSenders(): array
    {
        return $this->senders;
    }
}
