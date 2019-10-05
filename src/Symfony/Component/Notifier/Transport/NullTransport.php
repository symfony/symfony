<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
class NullTransport implements TransportInterface
{
    public function send(MessageInterface $message): void
    {
    }

    public function __toString(): string
    {
        return 'null';
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }
}
