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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
interface SmsRecipientInterface
{
    /**
     * Sets the phone number (no spaces, international code like in +3312345678).
     *
     * @return $this
     */
    public function phone(string $phone): self;

    public function getPhone(): string;
}
