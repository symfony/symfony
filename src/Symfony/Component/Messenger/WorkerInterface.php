<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * Interface for Workers that handle messages from transports.
 *
 * @experimental in 4.3
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface WorkerInterface
{
    /**
     * Receive the messages and dispatch them to the bus.
     *
     * The $onHandledCallback will be passed the Envelope that was just
     * handled or null if nothing was handled.
     *
     * @param mixed[] $options options used to control worker behavior
     */
    public function run(array $options = [], callable $onHandledCallback = null): void;

    /**
     * Stop receiving messages.
     */
    public function stop(): void;
}
