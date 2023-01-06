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
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
interface SmsRecipientInterface extends RecipientInterface
{
    public function getPhone(): string;
}
