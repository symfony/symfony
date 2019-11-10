<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
trait MessageRecordingEntityTrait
{
    /**
     * @var object[]
     */
    private $messages = [];

    /**
     * @see MessageRecordingEntityInterface::dispatchMessages()
     */
    final public function dispatchMessages(callable $dispatcher): void
    {
        $dispatcher($this->messages);
        $this->messages = [];
    }

    final protected function recordMessage(object $message): void
    {
        $this->messages[] = $message;
    }
}
