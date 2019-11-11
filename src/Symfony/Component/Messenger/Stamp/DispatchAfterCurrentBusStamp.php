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
 * Marker item to tell this message should be handled in after the current bus has finished.
 *
 * @see \Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class DispatchAfterCurrentBusStamp implements NonSendableStampInterface
{
}
