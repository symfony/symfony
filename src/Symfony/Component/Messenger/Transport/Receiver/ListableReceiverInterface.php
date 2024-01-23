<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

use Symfony\Component\Messenger\Envelope;

/**
 * Used when a receiver has the ability to list messages and find specific messages.
 * A receiver that implements this should add the TransportMessageIdStamp
 * to the Envelopes that it returns.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface ListableReceiverInterface extends ReceiverInterface
{
    /**
     * Returns all the messages (up to the limit) in this receiver.
     *
     * Messages should be given the same stamps as when using ReceiverInterface::get().
     *
     * @return Envelope[]|iterable
     */
    public function all(?int $limit = null): iterable;

    /**
     * Returns the Envelope by id or none.
     *
     * Message should be given the same stamps as when using ReceiverInterface::get().
     */
    public function find($id): ?Envelope;
}
