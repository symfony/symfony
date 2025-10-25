<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Envelope;

use Symfony\Component\Mime\Address;

interface RecipientFetcherInterface
{
    /**
     * @return array<Address|string> The list of recipients addresses
     */
    public function fetchRecipients(): array;
}
