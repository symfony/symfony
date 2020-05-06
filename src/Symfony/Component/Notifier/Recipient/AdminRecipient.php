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
class AdminRecipient extends Recipient implements SmsRecipientInterface
{
    private $phone;

    public function __construct(string $email = '', string $phone = '')
    {
        parent::__construct($email);

        $this->phone = $phone;
    }

    /**
     * @return $this
     */
    public function phone(string $phone): SmsRecipientInterface
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }
}
