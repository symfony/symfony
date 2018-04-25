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
 * A decorated message bus.
 *
 * Use this abstract class to created your message bus decorator to specialise your
 * bus instances and type-hint them.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
abstract class DecoratedMessageBus implements MessageBusInterface
{
    private $decoratedBus;

    public function __construct(MessageBusInterface $decoratedBus)
    {
        $this->decoratedBus = $decoratedBus;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message)
    {
        return $this->decoratedBus->dispatch($message);
    }
}
