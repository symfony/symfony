<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\PasswordHasher\Hasher\MigratingPasswordHasher;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', MigratingPasswordEncoder::class, MigratingPasswordHasher::class);

/**
 * Hashes passwords using the best available encoder.
 * Validates them using a chain of encoders.
 *
 * /!\ Don't put a PlaintextPasswordEncoder in the list as that'd mean a leaked hash
 * could be used to authenticate successfully without knowing the cleartext password.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 5.3, use {@link MigratingPasswordHasher} instead
 */
final class MigratingPasswordEncoder extends BasePasswordEncoder implements SelfSaltingEncoderInterface
{
    private $bestEncoder;
    private $extraEncoders;

    public function __construct(PasswordEncoderInterface $bestEncoder, PasswordEncoderInterface ...$extraEncoders)
    {
        $this->bestEncoder = $bestEncoder;
        $this->extraEncoders = $extraEncoders;
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword(string $raw, ?string $salt): string
    {
        return $this->bestEncoder->encodePassword($raw, $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid(string $encoded, string $raw, ?string $salt): bool
    {
        if ($this->bestEncoder->isPasswordValid($encoded, $raw, $salt)) {
            return true;
        }

        if (!$this->bestEncoder->needsRehash($encoded)) {
            return false;
        }

        foreach ($this->extraEncoders as $encoder) {
            if ($encoder->isPasswordValid($encoded, $raw, $salt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $encoded): bool
    {
        return $this->bestEncoder->needsRehash($encoded);
    }
}
