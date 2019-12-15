<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\ListenerProvider;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * A lazy proxy for listener providers.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class LazyListenerProvider implements ListenerProviderInterface
{
    private $factory;

    /**
     * @var ListenerProviderInterface
     */
    private $delegate;

    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (!$this->delegate) {
            $this->delegate = ($this->factory)();
        }

        return $this->delegate->getListenersForEvent($event);
    }

    public function __call(string $name, array $arguments)
    {
        if (!$this->delegate) {
            $this->delegate = ($this->factory)();
        }

        return $this->delegate->$name(...$arguments);
    }
}
