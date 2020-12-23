<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Recipient;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class Recipient implements EmailRecipientInterface, SmsRecipientInterface
{
    use EmailRecipientTrait;
    use SmsRecipientTrait;

    public function __construct(string $email = '', string $phone = '')
    {
        if ('' === $email && '' === $phone) {
            throw new InvalidArgumentException(sprintf('"%s" needs an email or a phone but both cannot be empty.', static::class));
        }

        $this->email = $email;
        $this->phone = $phone;
    }

    /**
     * @return $this
     */
    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Sets the phone number (no spaces, international code like in +3312345678).
     *
     * @return $this
     */
    public function phone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }
}
