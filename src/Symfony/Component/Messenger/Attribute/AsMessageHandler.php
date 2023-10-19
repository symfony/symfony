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
        public ?string $bus = null,
        public ?string $fromTransport = null,
        public ?string $handles = null,
        public ?string $method = null,
        public int $priority = 0,
    ) {
    }
}
