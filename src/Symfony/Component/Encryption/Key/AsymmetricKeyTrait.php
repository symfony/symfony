<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Key;

use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * Shared construction, accessors, and (de)serialization for the asymmetric key
 * value objects. Each consuming class defines its own MAGIC.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
trait AsymmetricKeyTrait
{
    private const VERSION = 1;
    private const HEADER_BYTES = 6; // magic(3) + version(1) + algorithmId(1) + purposeId(1)

    /** @var array<string, int> */
    private const ALGORITHM_IDS = ['x25519' => 1, 'rsa' => 2, 'ed25519' => 3, 'ecdsa-p256' => 4];

    /** @var array<string, int> */
    private const PURPOSE_IDS = ['encryption' => 1, 'signing' => 2];

    private function __construct(
        private readonly string $algorithm,
        private readonly string $purpose,
        private readonly string $bytes,
    ) {
        if (!isset(self::ALGORITHM_IDS[$algorithm])) {
            throw new InvalidKeyException(\sprintf('Unsupported key algorithm "%s".', $algorithm));
        }
        if (!isset(self::PURPOSE_IDS[$purpose])) {
            throw new InvalidKeyException(\sprintf('Unsupported key purpose "%s".', $purpose));
        }
        if ('' === $bytes) {
            throw new InvalidKeyException('Key material must not be empty.');
        }
    }

    #[\Override]
    public function algorithm(): string
    {
        return $this->algorithm;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    #[\Override]
    public function export(): string
    {
        return Encoding::toBase64(
            self::MAGIC
            . pack('C', self::VERSION)
            . pack('C', self::ALGORITHM_IDS[$this->algorithm])
            . pack('C', self::PURPOSE_IDS[$this->purpose])
            . $this->bytes,
        );
    }

    /**
     * @return array{string, string, string} algorithm, purpose, material
     */
    private static function parse(string $exported): array
    {
        try {
            $raw = Encoding::fromBase64($exported);
        } catch (InvalidArgumentException $e) {
            throw new InvalidKeyException('Malformed exported key.', 0, $e);
        }

        if (\strlen($raw) <= self::HEADER_BYTES || self::MAGIC !== substr($raw, 0, 3)) {
            throw new InvalidKeyException('Unrecognized or malformed key format.');
        }
        if (self::VERSION !== \ord($raw[3])) {
            throw new InvalidKeyException('Unsupported key version.');
        }

        $algorithm = array_search(\ord($raw[4]), self::ALGORITHM_IDS, true);
        $purpose = array_search(\ord($raw[5]), self::PURPOSE_IDS, true);
        if (false === $algorithm || false === $purpose) {
            throw new InvalidKeyException('Unsupported key algorithm or purpose.');
        }

        return [$algorithm, $purpose, substr($raw, self::HEADER_BYTES)];
    }
}
