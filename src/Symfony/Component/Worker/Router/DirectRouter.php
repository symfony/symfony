<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Router;

use Symfony\Component\Worker\Consumer\ConsumerInterface;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\MessageFetcherInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DirectRouter implements RouterInterface
{
    private $messageFetcher;
    private $consumer;

    public function __construct(MessageFetcherInterface $messageFetcher, ConsumerInterface $consumer)
    {
        $this->messageFetcher = $messageFetcher;
        $this->consumer = $consumer;
    }

    public function fetchMessages()
    {
        return $this->messageFetcher->fetchMessages();
    }

    public function consume(MessageCollection $messageCollection)
    {
        return $this->consumer->consume($messageCollection);
    }
}
