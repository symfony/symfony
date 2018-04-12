<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Adapter\AmqpExt;

use Symfony\Component\Messenger\Adapter\Factory\AdapterFactoryInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpAdapterFactory implements AdapterFactoryInterface
{
    private $encoder;
    private $decoder;
    private $debug;

    public function __construct(EncoderInterface $encoder, DecoderInterface $decoder, bool $debug)
    {
        $this->encoder = $encoder;
        $this->decoder = $decoder;
        $this->debug = $debug;
    }

    public function createReceiver(string $dsn, array $options): ReceiverInterface
    {
        return new AmqpReceiver($this->decoder, Connection::fromDsn($dsn, $options, $this->debug));
    }

    public function createSender(string $dsn, array $options): SenderInterface
    {
        return new AmqpSender($this->encoder, Connection::fromDsn($dsn, $options, $this->debug));
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'amqp://');
    }
}
