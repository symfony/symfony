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
 * Dispatched when a message was skip.
 *
 * The event name is the class name.
 */
final class WorkerMessageSkipEvent extends AbstractWorkerMessageEvent
{
}
