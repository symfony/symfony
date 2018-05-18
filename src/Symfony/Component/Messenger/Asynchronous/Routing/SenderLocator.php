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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class SenderLocator implements SenderLocatorInterface
{
    private $senderServiceLocator;
    private $messageToSenderIdsMapping;

    public function __construct(ContainerInterface $senderServiceLocator, array $messageToSenderIdsMapping)
    {
        $this->senderServiceLocator = $senderServiceLocator;
        $this->messageToSenderIdsMapping = $messageToSenderIdsMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getSendersForMessage($message): array
    {
        $senders = array();
        foreach ($this->getSenderIds($message) as $senderId) {
            $senders[] = $this->senderServiceLocator->get($senderId);
        }

        return $senders;
    }

    private function getSenderIds($message): array
    {
        if (isset($this->messageToSenderIdsMapping[\get_class($message)])) {
            return $this->messageToSenderIdsMapping[\get_class($message)];
        }
        if ($messageToSenderIdsMapping = array_intersect_key($this->messageToSenderIdsMapping, class_parents($message))) {
            return current($messageToSenderIdsMapping);
        }
        if ($messageToSenderIdsMapping = array_intersect_key($this->messageToSenderIdsMapping, class_implements($message))) {
            return current($messageToSenderIdsMapping);
        }
        if (isset($this->messageToSenderIdsMapping['*'])) {
            return $this->messageToSenderIdsMapping['*'];
        }

        return array();
    }
}
