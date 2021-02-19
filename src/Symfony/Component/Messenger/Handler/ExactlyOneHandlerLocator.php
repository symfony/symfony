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
use Symfony\Component\Messenger\Exception\MultipleHandlersForMessageException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

final class ExactlyOneHandlerLocator implements HandlersLocatorInterface
{
    private $handlers;

    public function __construct(array $handlers)
    {
        foreach ($handlers as $type => $h) {
            if (\count($h) > 1) {
                throw new MultipleHandlersForMessageException($type);
            }
        }

        $this->handlers = $handlers;
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        $type = \get_class($envelope->getMessage());
        if (!isset($this->handlers[$type])) {
            throw new NoHandlerForMessageException($type);
        }

        yield $this->handlers[$type][0];
    }
}
