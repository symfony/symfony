<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Sends chat messages, synchronously or through a message bus.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ChatterInterface extends TransportInterface
{
}
