<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

/**
 * Implemented by the execution strategies that start a message before the previous ones are handled.
 *
 * The worker reports the count to its listeners, so that the ones that reset
 * the state of the application can wait until no message is being handled.
 *
 * @author Jonathan H. Wage
 */
interface InFlightMessageCountAwareInterface
{
    /**
     * Returns the number of messages that were passed to the strategy and are not reported as handled yet.
     */
    public function getInFlightMessageCount(): int;
}
