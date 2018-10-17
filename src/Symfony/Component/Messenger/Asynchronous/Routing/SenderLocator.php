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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SenderLocator extends AbstractSenderLocator
{
    private $messageToSenderMapping;

    public function __construct(array $messageToSenderMapping)
    {
        $this->messageToSenderMapping = $messageToSenderMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getSender(Envelope $envelope): ?SenderInterface
    {
        $sender = self::getValueFromMessageRouting($this->messageToSenderMapping, $envelope);
        if (null === $sender) {
            return null;
        }

        if (!$sender instanceof SenderInterface) {
            throw new RuntimeException(sprintf('The sender instance provided for message "%s" should be of type "%s" but got "%s".', \get_class($envelope->getMessage()), SenderInterface::class, \is_object($sender) ? \get_class($sender) : \gettype($sender)));
        }

        return $sender;
    }
}
