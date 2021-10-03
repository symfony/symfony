<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MultipleHandlersForMessageException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

final class ExactlyOneHandlerLocator extends HandlersLocator
{
    /**
     * {@inheritdoc}
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $count = $this->countHandlers($envelope);
        if (0 === $count) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', \get_class($envelope->getMessage())));
        }

        if ($count > 1) {
            throw new MultipleHandlersForMessageException(sprintf('Multiple handlers for message "%s".', \get_class($envelope->getMessage())));
        }

        yield from $this->doGetHandlers($envelope);
    }
}
