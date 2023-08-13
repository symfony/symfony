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
    public function __construct(
        private readonly string $subscriberId,
        private readonly string|null $firstName = null,
        private readonly string|null $lastName = null,
        private readonly string|null $email = null,
        private readonly string|null $phone = null,
        private readonly string|null $avatar = null,
        private readonly string|null $locale = null,
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
}
