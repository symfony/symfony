<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * An envelope item related to a message.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.1
 */
interface EnvelopeItemInterface
{
    /**
     * @return bool True if this item can be transported. Otherwise, it'll be ignored during send.
     */
    public function isTransportable(): bool;
}
