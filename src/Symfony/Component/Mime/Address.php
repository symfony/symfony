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

    private static $validator;
    private static $encoder;

    private $address;
    private $name;

    public function __construct(string $address, string $name = '')
    {
        if (!class_exists(EmailValidator::class)) {
            throw new LogicException(sprintf('The "%s" class cannot be used as it needs "%s"; try running "composer require egulias/email-validator".', __CLASS__, EmailValidator::class));
        }

        if (null === self::$validator) {
            self::$validator = new EmailValidator();
        }

        $this->address = trim($address);
        $this->name = trim(str_replace(["\n", "\r"], '', $name));

        if (!self::$validator->isValid($this->address, new RFCValidation())) {
            throw new RfcComplianceException(sprintf('Email "%s" does not comply with addr-spec of RFC 2822.', $address));
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
        if (null === self::$encoder) {
            self::$encoder = new IdnAddressEncoder();
        }

        return self::$encoder->encodeString($this->address);
    }

    public function toString(): string
    {
        return ($n = $this->getName()) ? $n.' <'.$this->getEncodedAddress().'>' : $this->getEncodedAddress();
    }

    /**
     * @param Address|string $address
     */
    public static function create($address): self
    {
        if ($address instanceof self) {
            return $address;
        }
        if (\is_string($address)) {
            return new self($address);
        }

        throw new InvalidArgumentException(sprintf('An address can be an instance of Address or a string ("%s") given).', get_debug_type($address)));
    }

    /**
     * @param (Address|string)[] $addresses
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

    public static function fromString(string $string): self
    {
        if (false === strpos($string, '<')) {
            return new self($string, '');
        }

        if (!preg_match(self::FROM_STRING_PATTERN, $string, $matches)) {
            throw new InvalidArgumentException(sprintf('Could not parse "%s" to a "%s" instance.', $string, static::class));
        }

        return new self($matches['addrSpec'], trim($matches['displayName'], ' \'"'));
    }
}
