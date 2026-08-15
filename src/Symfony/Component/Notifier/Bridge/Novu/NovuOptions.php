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

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Wouter van der Loop <woutervdl@toppy.nl>
 */
class NovuOptions implements MessageOptionsInterface
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
     * @param array{
     *            tenant?: string,
     *            app?: string,
     *            extra?: array{
     *                key?: string,
     *                value?: string[]
     *            }
     *        } $context
     *
     * @see https://docs.novu.co/platform/integrations/email#sending-email-overrides
     * @see https://docs.novu.co/platform/workflow/advanced-features/contexts/manage-contexts
     */
    public function __construct(
        private readonly ?string $subscriberId = null,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $avatar = null,
        private readonly ?string $locale = null,
        private readonly array $overrides = [],
        private readonly array $options = [],
        private readonly array $context = [],
    ) {
    }

    public function toArray(): array
    {
        return array_merge($this->options, [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'overrides' => $this->overrides,
            'context' => $this->context,
        ]);
    }

    public function getRecipientId(): ?string
    {
        return $this->subscriberId ?? null;
    }
}
