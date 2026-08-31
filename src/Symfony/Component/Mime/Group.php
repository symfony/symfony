<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

use Symfony\Component\Mime\Exception\InvalidArgumentException;

/**
 * A named group of mailboxes, as defined by RFC 5322 (3.4).
 *
 * A group is an address just like a mailbox is, so it goes wherever an "address-list" is expected.
 * To, Cc, Bcc and Reply-To have always accepted one, which is what makes
 * "To: undisclosed-recipients:;" representable:
 *
 *     $email->getHeaders()->addMailboxListHeader('To', [new Group('undisclosed-recipients')]);
 *
 * RFC 6854 additionally allows a group in From and Resent-From, where RFC 5322 expected a
 * "mailbox-list", and in Sender and Resent-Sender, where it expected a single "mailbox". It
 * classifies that use as "Limited Use" and states that user agents SHOULD NOT permit groups in
 * those fields in outgoing messages, so reach for it knowingly there. Symfony takes a group in
 * From; Sender keeps taking a single mailbox.
 *
 * A group with no mailbox in From leaves the message without an author address, which is needed
 * to generate the Message-ID and to address the SMTP envelope. Set a Sender next to it:
 *
 *     $email->getHeaders()->addMailboxListHeader('From', [new Group('Automated System')]);
 *     $email->sender('robot@example.com');
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class Group
{
    private string $name;
    private array $addresses;

    /**
     * @param array<Address|string> $addresses
     */
    public function __construct(string $name, array $addresses = [])
    {
        $this->name = trim(preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', $name));

        if ('' === $this->name) {
            throw new InvalidArgumentException('A group must have a display name.');
        }

        $this->addresses = Address::createArray($addresses);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Address[]
     */
    public function getAddresses(): array
    {
        return $this->addresses;
    }
}
