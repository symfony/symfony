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

use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SenderLocator extends AbstractSenderLocator
{
    private $topicToSenderMapping;

    public function __construct(array $topicToSenderMapping)
    {
        $this->topicToSenderMapping = $topicToSenderMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getSender(string $topic): ?SenderInterface
    {
        $sender = self::getValueFromMessageRouting($this->topicToSenderMapping, $topic);
        if (null === $sender) {
            return null;
        }

        if (!$sender instanceof SenderInterface) {
            throw new RuntimeException(sprintf('The sender instance provided for message "%s" should be of type "%s" but got "%s".', $topic, SenderInterface::class, \is_object($sender) ? \get_class($sender) : \gettype($sender)));
        }

        return $sender;
    }
}
