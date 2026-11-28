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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates the strategy a worker started by the messenger:consume command runs its messages with.
 *
 * @author Jonathan H. Wage
 */
interface MessageExecutionStrategyFactoryInterface
{
    /**
     * @param MessageBusInterface $bus         The bus the worker dispatches the received messages on
     * @param int                 $concurrency The value of the --concurrency option of the command
     */
    public function create(MessageBusInterface $bus, int $concurrency, ?LoggerInterface $logger = null): MessageExecutionStrategyInterface;
}
