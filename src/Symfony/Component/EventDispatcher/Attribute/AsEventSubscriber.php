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

/**
 * Service tag to autoconfigure event subscriber.
 *
 * @author Alexander Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsEventSubscriber
{
    public function __construct(
        public ?array $subscribedEvents = null,
    ) {
    }
}
