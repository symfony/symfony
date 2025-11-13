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
 * Attribute for configuring message routing.
 *
 * @author Pierre Rineau pierre.rineau@processus.org>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsMessage
{
    public function __construct(
        /**
         * Name of the transports to which the message should be routed.
         */
        public string|array|null $transport = null,
    ) {
    }
}
