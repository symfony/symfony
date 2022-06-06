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

use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', NativePasswordEncoder::class, NativePasswordHasher::class);

/**
 * Hashes passwords using password_hash().
 *
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 * @author Terje Bråten <terje@braten.be>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 5.3, use {@link NativePasswordHasher} instead
 */
final class NativePasswordEncoder implements PasswordEncoderInterface, SelfSaltingEncoderInterface
{
    use LegacyEncoderTrait;

    /**
     * @param string|null $algo An algorithm supported by password_hash() or null to use the stronger available algorithm
     */
    public function __construct(int $opsLimit = null, int $memLimit = null, int $cost = null, string $algo = null)
    {
        $this->hasher = new NativePasswordHasher($opsLimit, $memLimit, $cost, $algo);
    }
}
