<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface SenderInterface
{
    /**
     * Sends the given envelope.
     */
    public function send(Envelope $envelope): void;
}
