<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender\Locator;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ContainerSenderLocator extends AbstractSenderLocator
{
    private $senderServiceLocator;
    private $topicToSenderIdMapping;

    public function __construct(ContainerInterface $senderServiceLocator, array $topicToSenderIdMapping)
    {
        $this->senderServiceLocator = $senderServiceLocator;
        $this->topicToSenderIdMapping = $topicToSenderIdMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getSender(string $topic): ?SenderInterface
    {
        $senderId = self::getValueFromMessageRouting($this->topicToSenderIdMapping, $topic);

        return $senderId ? $this->senderServiceLocator->get($senderId) : null;
    }
}
