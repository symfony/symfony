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
use Symfony\Component\Mime\NamedAddress;

/**
 * A Mailbox list MIME Header for something like From, To, Cc, and Bcc (one or more named addresses).
 *
 * @author Chris Corbyn
 *
 * @experimental in 4.3
 */
final class MailboxListHeader extends AbstractHeader
{
    private $addresses = [];

    /**
     * @param (NamedAddress|Address)[] $addresses
     */
    public function __construct(string $name, array $addresses)
    {
        parent::__construct($name);

        $this->setAddresses($addresses);
    }

    /**
     * @param (NamedAddress|Address)[] $body
     *
     * @throws RfcComplianceException
     */
    public function setBody($body)
    {
        $this->setAddresses($body);
    }

    /**
     * @throws RfcComplianceException
     *
     * @return (NamedAddress|Address)[]
     */
    public function getBody()
    {
        return $this->getAddresses();
    }

    /**
     * Sets a list of addresses to be shown in this Header.
     *
     * @param (NamedAddress|Address)[] $addresses
     *
     * @throws RfcComplianceException
     */
    public function setAddresses(array $addresses)
    {
        $this->addresses = [];
        $this->addAddresses($addresses);
    }

    /**
     * Sets a list of addresses to be shown in this Header.
     *
     * @param (NamedAddress|Address)[] $addresses
     *
     * @throws RfcComplianceException
     */
    public function addAddresses(array $addresses)
    {
        foreach ($addresses as $address) {
            $this->addAddress($address);
        }
    }

    /**
     * @throws RfcComplianceException
     */
    public function addAddress(Address $address)
    {
        $this->addresses[] = $address;
    }

    /**
     * @return (NamedAddress|Address)[]
     */
    public function getAddresses(): array
    {
        return $this->addresses;
    }

    /**
     * Gets the full mailbox list of this Header as an array of valid RFC 2822 strings.
     *
     * @throws RfcComplianceException
     *
     * @return string[]
     */
    public function getAddressStrings(): array
    {
        $strings = [];
        foreach ($this->addresses as $address) {
            $str = $address->getEncodedAddress();
            if ($address instanceof NamedAddress && $name = $address->getName()) {
                $str = $this->createPhrase($this, $name, $this->getCharset(), empty($strings)).' <'.$str.'>';
            }
            $strings[] = $str;
        }

        return $strings;
    }

    public function getBodyAsString(): string
    {
        return implode(', ', $this->getAddressStrings());
    }

    /**
     * Redefine the encoding requirements for addresses.
     *
     * All "specials" must be encoded as the full header value will not be quoted
     *
     * @see RFC 2822 3.2.1
     */
    protected function tokenNeedsEncoding(string $token): bool
    {
        return preg_match('/[()<>\[\]:;@\,."]/', $token) || parent::tokenNeedsEncoding($token);
    }
}
