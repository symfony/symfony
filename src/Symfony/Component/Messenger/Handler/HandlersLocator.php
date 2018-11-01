<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\TopicsResolver\TopicsResolverInterface;

/**
 * Maps a message to a list of handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
class HandlersLocator implements HandlersLocatorInterface
{
    private $topicsResolver;
    private $handlers;

    /**
     * @param callable[][] $handlers
     */
    public function __construct(TopicsResolverInterface $topicsResolver, array $handlers)
    {
        $this->topicsResolver = $topicsResolver;
        $this->handlers = $handlers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $seen = array();

        foreach ($this->topicsResolver->getTopics($envelope) as $topic) {
            foreach ($this->handlers[$topic] ?? array() as $handler) {
                if (!\in_array($handler, $seen, true)) {
                    yield $seen[] = $handler;
                }
            }
        }

        foreach ($this->handlers['*'] ?? array() as $handler) {
            if (!\in_array($handler, $seen, true)) {
                yield $seen[] = $handler;
            }
        }
    }
}
