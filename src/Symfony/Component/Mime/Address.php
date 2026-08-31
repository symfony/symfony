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

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\MessageIDValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\Mime\Encoder\IdnAddressEncoder;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Address
{
    /**
     * A regex that matches a structure like 'Name <email@address.com>'.
     * It matches anything between the first < and last > as email address.
     * This allows to use a single string to construct an Address, which can be convenient to use in
     * config, and allows to have more readable config.
     * This does not try to cover all edge cases for address.
     */
    private const FROM_STRING_PATTERN = '~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~';

    private static EmailValidator $validator;
    private static IdnAddressEncoder $encoder;

    private string $address;
    private string $name;

    public function __construct(string $address, string $name = '')
    {
        if (!class_exists(EmailValidator::class)) {
            throw new LogicException(\sprintf('The "%s" class cannot be used as it needs "%s". Try running "composer require egulias/email-validator".', __CLASS__, EmailValidator::class));
        }

        self::$validator ??= new EmailValidator();

        $this->address = trim($address);
        $this->name = trim(preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', $name));

        if (preg_match('/[\x00-\x1F\x7F]/', $this->address)) {
            throw new InvalidArgumentException('Email address contains control characters.');
        }

        if (!self::isValidAddrSpec($this->address)) {
            throw new RfcComplianceException(\sprintf('Email "%s" does not comply with addr-spec of RFC 2822.', $address));
        }
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEncodedAddress(): string
    {
        self::$encoder ??= new IdnAddressEncoder();

        return self::$encoder->encodeString($this->address);
    }

    public function toString(): string
    {
        return ($n = $this->getEncodedName()) ? $n.' <'.$this->getEncodedAddress().'>' : $this->getEncodedAddress();
    }

    public function getEncodedName(): string
    {
        if ('' === $this->getName()) {
            return '';
        }

        return \sprintf('"%s"', preg_replace('/["\\\\]/', '\\\\$0', $this->getName()));
    }

    public static function create(self|string $address): self
    {
        if ($address instanceof self) {
            return $address;
        }

        if (!str_contains($address, '<')) {
            return new self($address);
        }

        if (!preg_match(self::FROM_STRING_PATTERN, $address, $matches)) {
            throw new InvalidArgumentException(\sprintf('Could not parse "%s" to a "%s" instance.', $address, self::class));
        }

        return new self($matches['addrSpec'], trim($matches['displayName'], ' \'"'));
    }

    /**
     * @param array<Address|string> $addresses
     *
     * @return Address[]
     */
    public static function createArray(array $addresses): array
    {
        $addrs = [];
        foreach ($addresses as $address) {
            $addrs[] = self::create($address);
        }

        return $addrs;
    }

    private static function isValidAddrSpec(string $address): bool
    {
        // the message id validation is needed as this class also holds the ids of the Message-ID,
        // In-Reply-To and References headers, but it accepts an unquoted "@" in the local part
        if (!self::$validator->isValid($address, class_exists(MessageIDValidation::class) ? new MessageIDValidation() : new RFCValidation())) {
            return false;
        }

        // an address that already complies with the addr-spec needs no further check: the extra
        // "@" then belongs to the domain, e.g. inside a domain-literal (RFC 5322, 3.4.1)
        if (substr_count($address, '@') < 2 || self::$validator->isValid($address, new RFCValidation())) {
            return true;
        }

        return self::$validator->isValid(substr($address, 0, strrpos($address, '@')).'@example.com', new RFCValidation());
    }
}
