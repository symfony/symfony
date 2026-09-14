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

use Symfony\Component\Messenger\Middleware\PropagateStampsMiddleware;

/**
 * A stamp that is copied onto every message dispatched while the message carrying it is being handled.
 *
 * @see PropagateStampsMiddleware
 */
interface PropagatedStampInterface extends StampInterface
{
}
