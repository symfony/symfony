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

use Psr\Container\ContainerInterface;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Channel\SmsChannel;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Notifier implements NotifierInterface
{
    private array $adminRecipients = [];
    private array|ContainerInterface $channels;
    private ?ChannelPolicyInterface $policy;

    /**
     * @param ChannelInterface[]|ContainerInterface $channels
     */
    public function __construct(array|ContainerInterface $channels, ChannelPolicyInterface $policy = null)
    {
        $this->channels = $channels;
        $this->policy = $policy;
    }

    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        if (!$recipients) {
            $recipients = [new NoRecipient()];
        }

        foreach ($recipients as $recipient) {
            foreach ($this->getChannels($notification, $recipient) as $channel => $transportName) {
                $channel->notify($notification, $recipient, $transportName);
            }
        }
    }

    public function addAdminRecipient(RecipientInterface $recipient): void
    {
        $this->adminRecipients[] = $recipient;
    }

    /**
     * @return RecipientInterface[]
     */
    public function getAdminRecipients(): array
    {
        return $this->adminRecipients;
    }

    /**
     * @return iterable<ChannelInterface, string|null>
     */
    private function getChannels(Notification $notification, RecipientInterface $recipient): iterable
    {
        $channels = $notification->getChannels($recipient);
        if (!$channels) {
            $errorPrefix = sprintf('Unable to determine which channels to use to send the "%s" notification', \get_class($notification));
            $error = 'you should either pass channels in the constructor, override its "getChannels()" method';
            if (null === $this->policy) {
                throw new LogicException(sprintf('%s; %s, or configure a "%s".', $errorPrefix, $error, ChannelPolicy::class));
            }
            if (!$channels = $this->policy->getChannels($notification->getImportance())) {
                throw new LogicException(sprintf('%s; the "%s" returns no channels for importance "%s"; %s.', $errorPrefix, ChannelPolicy::class, $notification->getImportance(), $error));
            }
        }

        foreach ($channels as $channelName) {
            $transportName = null;
            if (false !== $pos = strpos($channelName, '/')) {
                $transportName = substr($channelName, $pos + 1);
                $channelName = substr($channelName, 0, $pos);
            }

            if (null === $channel = $this->getChannel($channelName)) {
                throw new LogicException(sprintf('The "%s" channel does not exist.', $channelName));
            }

            if ($channel instanceof SmsChannel && $recipient instanceof NoRecipient) {
                throw new LogicException(sprintf('The "%s" channel needs a Recipient.', $channelName));
            }

            if (!$channel->supports($notification, $recipient)) {
                throw new LogicException(sprintf('The "%s" channel is not supported.', $channelName));
            }

            yield $channel => $transportName;
        }
    }

    private function getChannel(string $name): ?ChannelInterface
    {
        if ($this->channels instanceof ContainerInterface) {
            return $this->channels->has($name) ? $this->channels->get($name) : null;
        }

        return $this->channels[$name] ?? null;
    }
}
