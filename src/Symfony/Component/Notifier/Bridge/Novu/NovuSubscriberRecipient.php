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
        if ([] !== $overrides) {
            trigger_deprecation('symfony/novu-notifier', '8.2', 'Passing "$overrides" to "%s()" is deprecated, pass them to "%s" instead.', __METHOD__, NovuOptions::class);
        }
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

    /**
     * @deprecated since Symfony 8.2, pass overrides to NovuOptions instead
     */
    public function getOverrides(): array
    {
        trigger_deprecation('symfony/novu-notifier', '8.2', 'The "%s()" method is deprecated, pass overrides to "%s" instead.', __METHOD__, NovuOptions::class);

        return $this->overrides;
    }
}
