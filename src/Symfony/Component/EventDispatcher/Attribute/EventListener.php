<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Attribute;

use Symfony\Contracts\Service\Attribute\TagInterface;

/**
 * Service tag to autoconfigure event listeners.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class EventListener implements TagInterface
{
    public function __construct(
        private ?string $event = null,
        private ?string $method = null,
        private int $priority = 0
    ) {
    }

    public function getName(): string
    {
        return 'kernel.event_listener';
    }

    public function getAttributes(): array
    {
        return [
            'event' => $this->event,
            'method' => $this->method,
            'priority' => $this->priority,
        ];
    }
}
