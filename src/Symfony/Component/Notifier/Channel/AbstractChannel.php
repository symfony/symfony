<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Channel;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractChannel implements ChannelInterface
{
    protected $transport;
    protected $bus;

    public function __construct(?TransportInterface $transport = null, ?MessageBusInterface $bus = null)
    {
        if (null === $transport && null === $bus) {
            throw new LogicException(sprintf('"%s" needs a Transport or a Bus but both cannot be "null".', static::class));
        }

        $this->transport = $transport;
        $this->bus = $bus;
    }
}
