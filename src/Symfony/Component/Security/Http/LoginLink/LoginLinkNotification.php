<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink;

use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

/**
 * Use this notification to ease sending login link
 * emails/SMS using the Notifier component.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LoginLinkNotification extends Notification implements EmailNotificationInterface, SmsNotificationInterface
{
    public function __construct(
        private LoginLinkDetails $loginLinkDetails,
        string $subject,
        array $channels = [],
    ) {
        parent::__construct($subject, $channels);
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        if (!class_exists(NotificationEmail::class)) {
            throw new \LogicException(\sprintf('The "%s()" method requires symfony/twig-bridge. Try running "composer require symfony/twig-bridge".', __METHOD__));
        }

        $email = NotificationEmail::asPublicEmail()
            ->to($recipient->getEmail())
            ->subject($this->getSubject())
            ->content($this->getContent() ?: $this->getDefaultContent('button below'))
            ->action('Sign in', $this->loginLinkDetails->getUrl())
        ;

        return new EmailMessage($email);
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage($recipient->getPhone(), $this->getDefaultContent('link').' '.$this->loginLinkDetails->getUrl());
    }

    private function getDefaultContent(string $target): string
    {
        $duration = $this->loginLinkDetails->getExpiresAt()->getTimestamp() - time();
        $durationString = floor($duration / 60).' minute'.($duration > 60 ? 's' : '');
        if (($hours = $duration / 3600) >= 1) {
            $durationString = floor($hours).' hour'.($hours >= 2 ? 's' : '');
        }

        return \sprintf('Click on the %s to confirm you want to sign in. This link will expire in %s.', $target, $durationString);
    }
}
