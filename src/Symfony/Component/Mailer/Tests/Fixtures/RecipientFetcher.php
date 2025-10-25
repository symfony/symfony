<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Fixtures;

use Symfony\Component\Mailer\Envelope\RecipientFetcherInterface;
use Symfony\Component\Mime\Address;

class RecipientFetcher implements RecipientFetcherInterface
{
    /**
     * @return array<Address|string>
     */
    public function fetchRecipients(): array
    {
        return ['fetched@example.com', new Address('fetched@symfony.com')];
    }
}
