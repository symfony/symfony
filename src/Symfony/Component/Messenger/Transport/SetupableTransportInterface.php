<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
interface SetupableTransportInterface
{
    /**
     * Setup the transport.
     */
    public function setup(): void;
}
