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

use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', SodiumPasswordEncoder::class, SodiumPasswordHasher::class);

/**
 * Hashes passwords using libsodium.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Zan Baldwin <hello@zanbaldwin.com>
 * @author Dominik Müller <dominik.mueller@jkweb.ch>
 *
 * @deprecated since Symfony 5.3, use {@link SodiumPasswordHasher} instead
 */
final class SodiumPasswordEncoder implements PasswordEncoderInterface, SelfSaltingEncoderInterface
{
    use LegacyEncoderTrait;

    public function __construct(int $opsLimit = null, int $memLimit = null)
    {
        $this->hasher = new SodiumPasswordHasher($opsLimit, $memLimit);
    }

    public static function isSupported(): bool
    {
        return SodiumPasswordHasher::isSupported();
    }
}
