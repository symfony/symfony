<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Wouter van der Loop <woutervdl@toppy.nl>
 */
class NovuSubscriberRecipient implements RecipientInterface
{
    /**
     * @param array{
     *            email?: array{
     *                from?: string,
     *                senderName?: string,
     *                replyTo?: string,
     *                cc?: string[],
     *                bcc?: string[]
     *            }|null
     *        } $overrides
     *
     * @see https://docs.novu.co/channels/email/#sending-email-overrides
     */
    public function __construct(
        private readonly string $subscriberId,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $avatar = null,
        private readonly ?string $locale = null,
        private readonly array $overrides = [],
    ) {
    }

    public function getSubscriberId(): string
    {
        return $this->subscriberId;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getOverrides(): array
    {
        return $this->overrides;
    }
}
