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
 * Dispatched after a message has been sent for retry.
 *
 * The event name is the class name.
 */
final class WorkerMessageRetriedEvent extends AbstractWorkerMessageEvent
{
}
