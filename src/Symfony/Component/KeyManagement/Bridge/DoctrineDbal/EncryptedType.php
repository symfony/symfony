<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Doctrine DBAL Type that decorates another Type with column-level
 * envelope encryption.
 *
 * The value first goes through the parent Type's `convertToDatabaseValue()`
 * (so any normalization, JSON encoding, datetime formatting, ... applies as
 * usual) before being wrapped in an {@see Envelope} produced by an
 * {@see EnvelopeEncrypterInterface}. The resulting bytes are stored in a
 * binary column. On the read path, the bytes are parsed back into an
 * Envelope, decrypted, then handed to the parent Type's
 * `convertToPHPValue()`.
 *
 * A binary value travels as a stream wherever the platform or the parent Type
 * works that way, a `BLOB` column read back by pdo_pgsql or a
 * {@see \Doctrine\DBAL\Types\BlobType} handing its value over. Both paths
 * settle it into bytes before doing anything else with it.
 *
 * What `$key` names, and what a row ends up carrying, is decided by the
 * encrypter given to this type, exactly as {@see EnvelopeEncrypterInterface}
 * describes. With an {@see EnvelopeEncrypter} it is a master key: every row
 * carries its own data encryption key wrapped inside its envelope, and the KMS
 * is consulted on every read and every write. With a
 * {@see StoredEnvelopeEncrypter} it is a scope: rows of that scope share a
 * stored data key and carry a 16-byte reference to it, so the KMS is consulted
 * once per data key and per process, and that key can later be rewrapped under
 * another provider without rewriting a single row.
 *
 * Needs the `Type` constructor that DBAL 4.3 unsealed, so that a subclass may
 * declare its own parameters.
 *
 * The column is always declared unbounded (`LONGBLOB`, `BYTEA`, ...), whatever
 * `length` the mapping declares: that length describes the plaintext, and the
 * ciphertext outgrows it by the envelope's framing, so a column sized after it
 * would truncate the ciphertext and destroy the authentication tag with it.
 *
 * Register one instance per (parent type, key) pair:
 *
 *     $type = new EncryptedType(
 *         Type::getTypeRegistry()->get('string'),
 *         $container->get(EnvelopeEncrypterInterface::class),
 *         'alias/app-key',
 *     );
 *     Type::getTypeRegistry()->register('app_user_email', $type);
 *
 * Then on the entity:
 *
 *     #[ORM\Column(type: 'app_user_email')]
 *     public string $email;
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class EncryptedType extends Type
{
    use BinaryColumn;

    public function __construct(
        private readonly Type $parentType,
        private readonly EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $envelopes,
        private readonly string $key,
    ) {
    }

    public function convertToDatabaseValue(#[\SensitiveParameter] mixed $value, AbstractPlatform $platform): mixed
    {
        $plaintext = $this->parentType->convertToDatabaseValue($value, $platform);
        if (null === $plaintext) {
            return null;
        }

        return (string) $this->envelopes->encrypt($this->key, self::bytes($plaintext));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return $this->parentType->convertToPHPValue(null, $platform);
        }

        $bytes = self::bytes($value);
        if ('' === $bytes) {
            return $this->parentType->convertToPHPValue($bytes, $platform);
        }

        try {
            $envelope = Envelope::fromBytes($bytes);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Stored value is not a valid KeyManagement envelope.', previous: $e);
        }

        return $this->parentType->convertToPHPValue($this->envelopes->decrypt($envelope), $platform);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        unset($column['length']);

        return $platform->getBlobTypeDeclarationSQL($column);
    }
}
