<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip;

use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Resolves scheme names used by KMIP DSNs and stored ciphertext.
 *
 * Each registered scheme has a unique, short ASCII name. A DSN selects a scheme for new encryption, while the name in a ciphertext selects the scheme used for decryption. Keep each name mapped to the same algorithm and mode while its ciphertext is stored. AES-GCM stores its IV length per ciphertext and can change that setting.
 *
 * The bundle fills this registry with its built-in services and tagged application implementations. Direct users pass the schemes they need to the constructor.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class KmipEncryptionSchemeRegistry
{
    /**
     * The ciphertext format stores this length in one unsigned byte.
     */
    private const int MAX_SCHEME_NAME_LENGTH = 255;
    /**
     * A lowercase scheme name may have one slash-separated qualifier.
     */
    private const string SCHEME_NAME_PATTERN = '/^[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?$/D';

    /**
     * @var array<string, KmipEncryptionSchemeInterface>
     */
    private array $schemes = [];

    /**
     * @param iterable<KmipEncryptionSchemeInterface> $schemes
     */
    public function __construct(iterable $schemes)
    {
        foreach ($schemes as $scheme) {
            $name = $scheme->name();
            if (!preg_match(self::SCHEME_NAME_PATTERN, $name) || \strlen($name) > self::MAX_SCHEME_NAME_LENGTH) {
                throw new InvalidArgumentException('A KMIP encryption scheme name must be a short lowercase ASCII identifier.');
            }
            if (isset($this->schemes[$name])) {
                throw new InvalidArgumentException(\sprintf('Duplicate KMIP encryption scheme "%s".', $name));
            }
            $this->schemes[$name] = $scheme;
        }
    }

    /**
     * @throws InvalidArgumentException If no scheme is registered with the given name
     */
    public function get(string $name): KmipEncryptionSchemeInterface
    {
        return $this->schemes[$name] ?? throw new InvalidArgumentException(\sprintf('Unknown KMIP encryption scheme "%s".', $name).' '.($this->schemes ? 'Supported schemes are '.implode(', ', array_keys($this->schemes)).'.' : 'No schemes are registered.'));
    }

    /**
     * Returns a registry with one scheme replaced, leaving other clients' registries unchanged.
     *
     * @throws InvalidArgumentException If the scheme name is not registered
     */
    public function withReplacement(KmipEncryptionSchemeInterface $scheme): self
    {
        $name = $scheme->name();
        if (!isset($this->schemes[$name])) {
            throw new InvalidArgumentException(\sprintf('Cannot replace unregistered KMIP encryption scheme "%s".', $name));
        }
        $schemes = $this->schemes;
        $schemes[$name] = $scheme;

        return new self($schemes);
    }
}
