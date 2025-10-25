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

final class AssignRecipients implements RecipientFetcherInterface
{
    /**
     * @param array<Address|string> $recipients
     */
    public function __construct(private readonly array $recipients)
    {
    }

    /**
     * @return array<Address|string>
     */
    public function fetchRecipients(): array
    {
        return $this->recipients;
    }
}
