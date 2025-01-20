<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Attribute;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsRemoteEventConsumer
{
    /**
     * @param string $name The name of the remote event consumer, used to identify it when defining remote events
     */
    public function __construct(
        public string $name,
    ) {
    }
}
