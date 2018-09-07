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
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class SenderLocator implements SenderLocatorInterface
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
    public function getSenderForMessage($message): ?SenderInterface
    {
        $senderId = $this->getSenderId($message);

        return $senderId ? $this->senderServiceLocator->get($senderId) : null;
    }

    private function getSenderId($message): ?string
    {
        return self::getValueFromMessageRouting($this->messageToSenderIdMapping, $message);
    }

    /**
     * @internal
     */
    public static function getValueFromMessageRouting(array $mapping, $message)
    {
        if (isset($mapping[\get_class($message)])) {
            return $mapping[\get_class($message)];
        }
        if ($parentsMapping = array_intersect_key($mapping, class_parents($message))) {
            return current($parentsMapping);
        }
        if ($interfaceMapping = array_intersect_key($mapping, class_implements($message))) {
            return current($interfaceMapping);
        }
        if (isset($mapping['*'])) {
            return $mapping['*'];
        }

        return null;
    }
}
