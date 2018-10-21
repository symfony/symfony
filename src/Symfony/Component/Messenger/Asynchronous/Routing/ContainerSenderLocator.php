<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Routing;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ContainerSenderLocator extends AbstractSenderLocator
{
    private $senderServiceLocator;
    private $messageToSenderIdMapping;

    public function __construct(ContainerInterface $senderServiceLocator, array $messageToSenderIdMapping)
    {
        $this->senderServiceLocator = $senderServiceLocator;
        $this->messageToSenderIdMapping = $messageToSenderIdMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getSender(Envelope $envelope): ?SenderInterface
    {
        $senderId = self::getValueFromMessageRouting($this->messageToSenderIdMapping, $envelope);

        return $senderId ? $this->senderServiceLocator->get($senderId) : null;
    }
}
