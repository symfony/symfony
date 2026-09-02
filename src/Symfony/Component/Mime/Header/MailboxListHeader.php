<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Header;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Group;

/**
 * A Mailbox list MIME Header for something like From, To, Cc, and Bcc (one or more named addresses).
 *
 * An entry is either a mailbox or, since RFC 6854, a group of mailboxes (RFC 5322, 3.4).
 *
 * @author Chris Corbyn
 */
final class MailboxListHeader extends AbstractHeader
{
    private array $addresses = [];

    /**
     * @param array<Address|Group> $addresses
     */
    public function __construct(string $name, array $addresses)
    {
        parent::__construct($name);

        $this->setAddresses($addresses);
    }

    /**
     * Normalizes an address-list, where an entry is either a mailbox or a group.
     *
     * @param array<Address|Group|string> $addresses
     *
     * @return list<Address|Group>
     *
     * @throws RfcComplianceException
     */
    public static function createAddressList(array $addresses): array
    {
        $list = [];
        foreach ($addresses as $address) {
            $list[] = $address instanceof Group ? $address : Address::create($address);
        }

        return $list;
    }

    /**
     * @param array<Address|Group> $body
     *
     * @throws RfcComplianceException
     */
    public function setBody(mixed $body): void
    {
        $this->setAddresses($body);
    }

    /**
     * @return list<Address>
     *
     * @throws RfcComplianceException
     */
    public function getBody(): array
    {
        return $this->getAddresses();
    }

    /**
     * Sets a list of addresses to be shown in this Header.
     *
     * @param array<Address|Group> $addresses
     *
     * @throws RfcComplianceException
     */
    public function setAddresses(array $addresses): void
    {
        $this->addresses = [];
        $this->addAddresses($addresses);
    }

    /**
     * Sets a list of addresses to be shown in this Header.
     *
     * @param array<Address|Group> $addresses
     *
     * @throws RfcComplianceException
     */
    public function addAddresses(array $addresses): void
    {
        foreach ($addresses as $address) {
            $this->addAddress($address);
        }
    }

    /**
     * @throws RfcComplianceException
     */
    public function addAddress(Address|Group $address): void
    {
        $this->addresses[] = $address;
    }

    /**
     * Gets every mailbox of this Header, the members of its groups included.
     *
     * @return list<Address>
     */
    public function getAddresses(): array
    {
        $mailboxes = [];
        foreach ($this->addresses as $address) {
            if ($address instanceof Group) {
                foreach ($address->getAddresses() as $mailbox) {
                    $mailboxes[] = $mailbox;
                }
            } else {
                $mailboxes[] = $address;
            }
        }

        return $mailboxes;
    }

    /**
     * Gets the address-list of this Header, where an entry is either a mailbox or a group.
     *
     * @return list<Address|Group>
     */
    public function getAddressList(): array
    {
        return $this->addresses;
    }

    /**
     * Gets the full mailbox list of this Header as an array of valid RFC 5322 strings.
     *
     * @return string[]
     *
     * @throws RfcComplianceException
     */
    public function getAddressStrings(): array
    {
        $strings = [];
        foreach ($this->addresses as $address) {
            if (!$address instanceof Group) {
                $strings[] = $this->createMailboxString($address, !$strings);

                continue;
            }

            $name = $this->createPhrase($this, $address->getName(), $this->getCharset(), !$strings);
            $mailboxes = [];
            foreach ($address->getAddresses() as $mailbox) {
                $mailboxes[] = $this->createMailboxString($mailbox, false);
            }

            $strings[] = $mailboxes ? $name.': '.implode(', ', $mailboxes).';' : $name.':;';
        }

        return $strings;
    }

    public function getBodyAsString(): string
    {
        return implode(', ', $this->getAddressStrings());
    }

    /**
     * @throws RfcComplianceException
     */
    private function createMailboxString(Address $address, bool $shorten): string
    {
        $str = $address->getEncodedAddress();
        if ($name = $address->getName()) {
            $str = $this->createPhrase($this, $name, $this->getCharset(), $shorten).' <'.$str.'>';
        }

        return $str;
    }

    /**
     * Redefine the encoding requirements for addresses.
     *
     * All "specials" must be encoded as the full header value will not be quoted
     *
     * @see RFC 5322 3.2.3
     */
    protected function tokenNeedsEncoding(string $token): bool
    {
        return preg_match('/[()<>\[\]:;@\,."]/', $token) || parent::tokenNeedsEncoding($token);
    }
}
