<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Attribute;

/**
 * Service tag to autoconfigure message handlers.
 *
 * @author Alireza Mirsepassi <alirezamirsepassi@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsMessageHandler
{
    public function __construct(
        /**
         * Name of the bus from which this handler can receive messages, by default all buses.
         */
        public ?string $bus = null,

        /**
         * Name of the transport from which this handler can receive messages, by default all transports.
         */
        public ?string $fromTransport = null,

        /**
         * Type of messages (FQCN) that can be processed by the handler, only needed if can't be guessed by type-hint.
         */
        public ?string $handles = null,

        /**
         * Name of the method that will process the message, only if the target is a class.
         */
        public ?string $method = null,

        /**
         * Priority of this handler when multiple handlers can process the same message.
         */
        public int $priority = 0,
    ) {
    }
}
