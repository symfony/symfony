<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Inbox;

/**
 * Records the messages that a consumer handles, so that a redelivered message is not handled twice.
 *
 * The record is written in the database transaction of the handlers.
 * It is committed or rolled back with the changes of the handlers.
 */
interface InboxInterface
{
    /**
     * Records that a consumer handles a message.
     *
     * @return bool false when the consumer recorded the message already
     */
    public function record(string $consumer, string $messageId): bool;

    /**
     * Removes the record of a message.
     */
    public function forget(string $consumer, string $messageId): void;
}
