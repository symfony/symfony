<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp marks that the handlers *should* be called immediately.
 *
 * This is used by the SyncTransport to indicate to the
 * SendMessageMiddleware that handlers *should* be called
 * immediately, even though a transport was set.
 *
 * @experimental in 4.3
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class ForceCallHandlersStamp implements StampInterface
{
}
