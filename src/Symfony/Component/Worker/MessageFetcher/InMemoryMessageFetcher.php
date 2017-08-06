<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\MessageFetcher;

use Symfony\Component\Worker\MessageCollection;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class InMemoryMessageFetcher implements MessageFetcherInterface
{
    private $messages;

    public function __construct(array $messages = array())
    {
        $this->messages = $messages;
    }

    public function fetchMessages()
    {
        if (!$this->messages) {
            return false;
        }

        $message = array_shift($this->messages);

        if (false === $message) {
            return false;
        }

        return new MessageCollection($message);
    }

    public function queueMessage($message)
    {
        $this->messages[] = $message;
    }
}
