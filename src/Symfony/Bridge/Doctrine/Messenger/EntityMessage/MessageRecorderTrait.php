<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger\EntityMessage;

/**
 * Use this trait in classes which implement EntityMessageCollectionInterface
 * to privately record and later release Message instances, like events.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
trait MessageRecorderTrait
{
    private $messages = [];

    /**
     * {@inheritdoc}
     */
    public function getRecordedMessages(): array
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function resetRecordedMessages(): void
    {
        $this->messages = [];
    }

    /**
     * Record a message.
     *
     * @param object $message
     */
    private function record($message): void
    {
        $this->messages[] = $message;
    }
}
