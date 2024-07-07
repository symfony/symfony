<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\JoliNotif;

use Joli\JoliNotif\DefaultNotifier as JoliNotifier;
use Joli\JoliNotif\Notification as JoliNotification;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class JoliNotifTransport extends AbstractTransport
{
    public function __construct(
        private readonly JoliNotifier $joliNotifier,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct(null, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('jolinotif://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof DesktopMessage && (null === $message->getOptions() || $message->getOptions() instanceof JoliNotifOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof DesktopMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, DesktopMessage::class, $message);
        }

        if (($options = $message->getOptions()) && !$options instanceof JoliNotifOptions) {
            throw new LogicException(\sprintf('The "%s" transport only supports an instance of the "%s" as an option class.', __CLASS__, JoliNotifOptions::class));
        }

        $joliNotification = $this->buildJoliNotificationObject($message, $options);

        if (false === $this->joliNotifier->send($joliNotification)) {
            throw new RuntimeException(\sprintf('An error occurred while sending a notification via the "%s" transport.', __CLASS__));
        }

        return new SentMessage($message, (string) $this);
    }

    private function buildJoliNotificationObject(DesktopMessage $message, ?JoliNotifOptions $options = null): JoliNotification
    {
        $joliNotification = new JoliNotification();

        $joliNotification->setTitle($message->getSubject());
        $joliNotification->setBody($message->getContent());

        if ($options) {
            if ($iconPath = $options->getIconPath()) {
                $joliNotification->setIcon($iconPath);
            }

            foreach ($options->getExtraOptions() as $extraOptionKey => $extraOptionValue) {
                $joliNotification->addOption($extraOptionKey, $extraOptionValue);
            }
        }

        return $joliNotification;
    }
}
