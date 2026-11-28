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
use Symfony\Component\Clock\Clock;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Use this notification to ease sending login link
 * emails/SMS using the Notifier component.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LoginLinkNotification extends Notification implements EmailNotificationInterface, SmsNotificationInterface
{
    /**
     * @param TranslatorInterface|null $translator Translates the default content and action label in the "security" domain
     * @param string|null              $locale     The translation locale, or null to use the translator's locale
     */
    public function __construct(
        private LoginLinkDetails $loginLinkDetails,
        string $subject,
        array $channels = [],
        private ?TranslatorInterface $translator = null,
        private ?string $locale = null,
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
            ->action(null !== $this->translator ? $this->translator->trans('Sign in', [], 'security', $this->locale) : 'Sign in', $this->loginLinkDetails->getUrl())
        ;

        return new EmailMessage($email);
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage($recipient->getPhone(), $this->getDefaultContent('link').' '.$this->loginLinkDetails->getUrl());
    }

    private function getDefaultContent(string $target): string
    {
        $duration = $this->loginLinkDetails->getExpiresAt()->getTimestamp() - (class_exists(Clock::class) ? Clock::get()->now()->getTimestamp() : time());
        if (null !== $this->translator) {
            if ($duration >= 3600) {
                $parameters = ['%count%' => (int) floor($duration / 3600)];

                return 'button below' === $target
                    ? $this->translator->trans('Click on the button below to confirm you want to sign in. This link will expire in %count% hour.|Click on the button below to confirm you want to sign in. This link will expire in %count% hours.', $parameters, 'security', $this->locale)
                    : $this->translator->trans('Click on the link to confirm you want to sign in. This link will expire in %count% hour.|Click on the link to confirm you want to sign in. This link will expire in %count% hours.', $parameters, 'security', $this->locale);
            }

            $parameters = ['%count%' => (int) floor($duration / 60)];

            return 'button below' === $target
                ? $this->translator->trans('Click on the button below to confirm you want to sign in. This link will expire in %count% minute.|Click on the button below to confirm you want to sign in. This link will expire in %count% minutes.', $parameters, 'security', $this->locale)
                : $this->translator->trans('Click on the link to confirm you want to sign in. This link will expire in %count% minute.|Click on the link to confirm you want to sign in. This link will expire in %count% minutes.', $parameters, 'security', $this->locale);
        }

        $durationString = floor($duration / 60).' minute'.($duration > 60 ? 's' : '');
        if (($hours = $duration / 3600) >= 1) {
            $durationString = floor($hours).' hour'.($hours >= 2 ? 's' : '');
        }

        return \sprintf('Click on the %s to confirm you want to sign in. This link will expire in %s.', $target, $durationString);
    }
}
