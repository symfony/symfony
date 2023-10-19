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
    public function __construct(
        private readonly string|null $subscriberId = null,
        private readonly string|null $firstName = null,
        private readonly string|null $lastName = null,
        private readonly string|null $email = null,
        private readonly string|null $phone = null,
        private readonly string|null $avatar = null,
        private readonly string|null $locale = null,
        private readonly array $options = [],
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
        ]);
    }

    public function getRecipientId(): ?string
    {
        return $this->subscriberId ?? null;
    }
}
