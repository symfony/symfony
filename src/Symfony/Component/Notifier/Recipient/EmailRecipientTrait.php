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
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
trait EmailRecipientTrait
{
    private $email;

    public function getEmail(): string
    {
        return $this->email;
    }
}
