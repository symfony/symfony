<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;

/**
 * Event dispatched after a handler fails.
 */
final class HandlerFailureEvent
{
    public function __construct(
        public readonly Envelope $envelope,
        public readonly HandlerDescriptor $handlerDescriptor,
        public readonly \Throwable $exception,
    ) {
    }
}
