<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\Senders;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Maps a message to a list of senders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SendersLocator implements SendersLocatorInterface
{
    private array $sendersMap;
    private ContainerInterface $sendersLocator;

    /**
     * @param array<string, list<string>> $sendersMap     An array, keyed by "type", set to an array of sender aliases
     * @param ContainerInterface          $sendersLocator Locator of senders, keyed by sender alias
     */
    public function __construct(array $sendersMap, ContainerInterface $sendersLocator)
    {
        $this->sendersMap = $sendersMap;
        $this->sendersLocator = $sendersLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenders(Envelope $envelope): iterable
    {
        $senderAliases = [];

        if (\PHP_VERSION_ID >= 80000) {
            $senderAliases = $this->getSendersFromAttributes($envelope);
        }

        foreach (HandlersLocator::listTypes($envelope) as $type) {
            foreach ($this->sendersMap[$type] ?? [] as $senderAlias) {
                $senderAliases[] = $senderAlias;
            }
        }

        $senderAliases = array_unique($senderAliases);

        foreach ($senderAliases as $senderAlias) {
            if (!$this->sendersLocator->has($senderAlias)) {
                throw new RuntimeException(sprintf('Invalid senders configuration: sender "%s" is not in the senders locator.', $senderAlias));
            }

            $sender = $this->sendersLocator->get($senderAlias);
            yield $senderAlias => $sender;
        }
    }

    /**
     * @return string[]
     */
    private function getSendersFromAttributes(Envelope $envelope): array
    {
        $messageClass = \get_class($envelope->getMessage());

        try {
            $reflectionClass = new \ReflectionClass($messageClass);
        } catch (\ReflectionException $e) {
            return [];
        }

        $attributes = $reflectionClass->getAttributes(Senders::class);

        $senders = [];
        foreach ($attributes as $attribute) {
            /** @var Senders $attributeInstance */
            $attributeInstance = $attribute->newInstance();
            $senders = array_merge($senders, $attributeInstance->senders);
        }

        return $senders;
    }
}
