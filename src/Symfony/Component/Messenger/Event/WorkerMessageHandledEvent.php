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

/**
 * Dispatched after a message was received from a transport and successfully handled.
 *
 * The event name is the class name.
 */
final class WorkerMessageHandledEvent extends AbstractWorkerMessageEvent
{
}
